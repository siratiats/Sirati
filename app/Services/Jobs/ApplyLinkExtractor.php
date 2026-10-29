<?php

namespace App\Services\Jobs;

/**
 * Decides how a candidate applies to an aggregated job: an employer link, an
 * email address, or nothing. Telegram, social profiles, channel promo links,
 * hashtag searches and URL shorteners are never an application destination;
 * the Telegram post stays available only as the job's `url` (its source).
 *
 * The same host rules guard the API payload, so rows stored before this
 * existed can never surface a denied link as "Apply".
 */
class ApplyLinkExtractor
{
    public const METHOD_URL = 'url';
    public const METHOD_EMAIL = 'email';
    public const METHOD_NONE = 'none';

    /** Hosts (and their subdomains) that are never an application page. */
    private const DENIED_HOSTS = [
        // Telegram
        't.me', 'telegram.me', 'telegram.org', 'telegram.dog', 'telesco.pe',
        // Social profiles / channel promotion
        'instagram.com', 'instagr.am', 'snapchat.com', 'x.com', 'twitter.com',
        'facebook.com', 'fb.com', 'fb.me', 'tiktok.com', 'youtube.com', 'youtu.be',
        'threads.net', 'whatsapp.com', 'wa.me',
        // Shorteners hide the destination; they are not resolved (SSRF surface).
        'bit.ly', 'lik.ad', 't.co', 'cutt.ly', 'tinyurl.com', 'goo.gl', 'rb.gy',
        'shorturl.at', 'is.gd', 'ow.ly', 'buff.ly', 'rebrand.ly', 'linktr.ee',
        'clck.ru', 's.id', 'tiny.cc', 'v.gd', 'cutt.us', 'bit.do', 't.ly',
        'short.gy', 'lnkd.in', 'surl.li', 'shorturl.asia', 'urlz.fr', 'qr.ae',
    ];

    /**
     * Hosts that are application pages by nature (government, Jadarat, ATS,
     * forms). Links to other hosts count only with an apply cue on their line
     * or a careers/jobs/apply path, so an employer homepage mentioned in the
     * intro is not taken as "Apply".
     */
    private const APPLY_HOSTS = [
        'jadarat.sa', 'qiwa.sa', 'gov.sa', 'edu.sa', 'forms.gle', 'forms.office.com',
        'forms.cloud.microsoft', 'greenhouse.io', 'lever.co', 'workable.com',
        'smartrecruiters.com', 'taleo.net', 'myworkdayjobs.com', 'successfactors.com',
        'successfactors.eu', 'bayt.com', 'recruitee.com', 'bamboohr.com', 'zohorecruit.com',
    ];

    private const APPLY_PATH_PATTERN = '#/(?:careers?|jobs?|vacanc(?:y|ies)|apply|application|recruit(?:ment|ing)?|forms?|employment|join-?us|opportunit(?:y|ies))(?:/|$|\.)#i';

    private const APPLY_SUBDOMAIN_PATTERN = '/^(?:careers?|jobs?|recruit(?:ment|ing)?|hr|apply)[.\-]/';

    private const APPLY_CUE_PATTERN = '/(?<!\p{L})و?(?:للتقديم|التقديم|قدّم|قدم|تقدّم|تقدم|رابط التسجيل|للتسجيل|التسجيل|apply|application|careers?)(?!\p{L})/iu';

    /** Lines that start with these words are channel promotion, not job text. */
    private const PROMO_LINE_PATTERN = '/^[\s\p{P}\p{S}\p{Cf}]*(?:'
        .'تابعونا|تابعنا|للاشتراك في|اشترك في|اشتركوا|انضم (?:إلى|الى|ل)?\s*(?:قناتنا|القناة|قروب|مجموعتنا)|انضموا|قناتنا|حسابنا|حساباتنا|رابط القناة|رابط الدعوة|رابط الانضمام|شارك(?:وا)? (?:الإعلان|القناة)|انشره|انشروا|'
        .'المصدر\s*:|كود خصم|كود الخصم|ان لم تكن بحاجة|إن لم تكن بحاجة|source:|'
        .'instagram|snapchat|snap|tiktok|twitter|telegram|whatsapp|youtube|facebook|انستقرام|انستغرام|سناب|تيك ?توك|تويتر|تليجرام|تيليجرام|تلغرام|واتساب|واتس|يوتيوب|فيسبوك'
        .')/iu';

    private const PROMO_TOKEN_PATTERN = '/(?:instagram|snapchat|tiktok|twitter|telegram|whatsapp|youtube|facebook|تابعونا|تابعنا|اشترك|انضم|قناتنا|انستقرام|انستغرام|سناب|تيك ?توك|تويتر|تليجرام|تيليجرام|تلغرام|يوتيوب|فيسبوك|كود خصم)/iu';

    private const EMAIL_PATTERN = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/u';

    /**
     * Extract the apply destination from a Telegram post's message-text HTML
     * (the `tgme_widget_message_text` element only, never the whole wrapper,
     * which also holds the channel's own avatar link). Only links on lines
     * that survive footer stripping count.
     *
     * @return array{method: string, url: ?string, email: ?string}
     */
    public function fromMessageHtml(string $messageHtml): array
    {
        $htmlLines = preg_split('/<br\s*\/?>|\R/iu', $messageHtml) ?: [];
        $textLines = array_map(fn (string $html): string => $this->htmlToText($html), $htmlLines);
        $kept = $this->keptLineCount($textLines);

        $contextual = null;
        $plain = null;
        for ($i = 0; $i < $kept; $i++) {
            if (! preg_match_all('/<a\b[^>]*href="([^"]+)"/iu', $htmlLines[$i], $m)) {
                continue;
            }
            // Promotion never applies; a "المصدر:" line counts only through a
            // careers-page link (checked below without a cue).
            $source = $this->isSourceLine($textLines[$i]);
            if (! $source && preg_match(self::PROMO_LINE_PATTERN, $textLines[$i])) {
                continue;
            }
            $cue = ! $source && preg_match(self::APPLY_CUE_PATTERN, $textLines[$i]);
            foreach ($m[1] as $href) {
                $href = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! $this->isAllowedApplyUrl($href)) {
                    continue;
                }
                if ($cue) {
                    $contextual ??= $href;
                } elseif ($this->looksLikeApplyPage($href)) {
                    $plain ??= $href;
                }
            }
        }

        if (($url = $contextual ?? $plain) !== null) {
            return ['method' => self::METHOD_URL, 'url' => $url, 'email' => null];
        }

        return $this->fromText(implode("\n", array_slice($textLines, 0, $kept)));
    }

    /** Visible text of an HTML fragment, entities decoded. */
    public function htmlToText(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Fall back to an email address written in the job text.
     *
     * @return array{method: string, url: ?string, email: ?string}
     */
    public function fromText(string $text): array
    {
        $email = $this->findEmail($this->stripPromoFooter($text));

        return $email !== null
            ? ['method' => self::METHOD_EMAIL, 'url' => null, 'email' => $email]
            : ['method' => self::METHOD_NONE, 'url' => null, 'email' => null];
    }

    /**
     * Resolve the method to expose for a stored row. A stored apply_url that
     * fails the current host rules is dropped, whatever wrote it.
     *
     * @return array{method: string, url: ?string, email: ?string}
     */
    public function resolve(?string $applyUrl, ?string $applyEmail): array
    {
        if ($applyUrl !== null && $this->isAllowedApplyUrl($applyUrl)) {
            return ['method' => self::METHOD_URL, 'url' => $applyUrl, 'email' => null];
        }

        $email = $applyEmail !== null ? $this->findEmail($applyEmail) : null;
        if ($email !== null) {
            return ['method' => self::METHOD_EMAIL, 'url' => null, 'email' => $email];
        }

        return ['method' => self::METHOD_NONE, 'url' => null, 'email' => null];
    }

    public function isAllowedApplyUrl(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if ($host === '' || ! str_contains($host, '.') || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            return false;
        }
        if ($this->hostIn($host, self::DENIED_HOSTS)) {
            return false;
        }

        $path = strtolower((string) ($parts['path'] ?? ''));
        $query = (string) ($parts['query'] ?? '');

        // Hashtag / search links (Telegram renders `#tag` as `?q=%23tag`).
        if (preg_match('/(^|&)q=(%23|#)/i', $query) || preg_match('#^/(search|hashtag)(/|$)#', $path)) {
            return false;
        }

        // LinkedIn is an application page only for job postings, not profiles or posts.
        if ($this->hostIn($host, ['linkedin.com'])) {
            return str_contains($path, '/jobs/');
        }

        return ! $this->looksLikeShortener($host, (string) ($parts['path'] ?? ''));
    }

    /**
     * Unlisted shorteners (dlvr.it/Ab12, xyz.co/k9F2): a very short host and a
     * single code-like path segment (it has a digit, or a capital after a
     * small letter), whatever the query. Plain words (`qiwa.sa/login`), known
     * apply hosts, careers-style subdomains (`hr.`, `careers.`) and careers
     * paths are not shorteners. Their destination is unknown, so they are
     * never "Apply".
     */
    private function looksLikeShortener(string $host, string $path): bool
    {
        if ($this->hostIn($host, self::APPLY_HOSTS)
            || preg_match(self::APPLY_SUBDOMAIN_PATTERN, $host)
            || preg_match(self::APPLY_PATH_PATTERN, $path)) {
            return false;
        }

        return mb_strlen(preg_replace('/^www\./', '', $host)) <= 10
            && preg_match('#^/([A-Za-z0-9_\-]{3,32})/?$#', $path, $m)
            && preg_match('/\d|[a-z][A-Z]/', $m[1]);
    }

    /** Known application hosts, or a careers/jobs/apply style path. */
    private function looksLikeApplyPage(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $this->hostIn($host, self::APPLY_HOSTS)
            || $this->hostIn($host, ['linkedin.com'])
            || (bool) preg_match(self::APPLY_SUBDOMAIN_PATTERN, $host)
            || (bool) preg_match(self::APPLY_PATH_PATTERN, (string) parse_url($url, PHP_URL_PATH));
    }

    /** @param list<string> $hosts */
    private function hostIn(string $host, array $hosts): bool
    {
        foreach ($hosts as $candidate) {
            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove trailing channel-promotion lines (follow-us blocks, social links,
     * a channel signature under a separator) from the end of a post. Only the
     * trailing block is cut, a line carrying a contact (email, phone, allowed
     * link) is never cut, and the result is stable when applied twice.
     */
    public function stripPromoFooter(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        return trim(implode("\n", array_slice($lines, 0, $this->keptLineCount($lines))));
    }

    /**
     * How many leading lines remain once the promo footer is removed.
     *
     * @param  list<string>  $lines
     */
    private function keptLineCount(array $lines): int
    {
        $count = count($lines);

        while ($count > 0) {
            $last = trim((string) $lines[$count - 1]);
            if ($last === '' || $this->isSeparatorLine($last) || $this->isPromoLine($last)) {
                $count--;
                continue;
            }

            // A one-line channel signature (e.g. "🔻وظائف السعودية") directly
            // under a separator: it must start with an emoji/symbol or carry a
            // promo token, so real requirement lines are never cut.
            if ($count >= 2
                && $this->isSeparatorLine(trim((string) $lines[$count - 2]))
                && ! $this->carriesContact($last)
                && mb_strlen($last) <= 40
                && (preg_match('/^[\p{Cf}\s]*\p{So}/u', $last) || preg_match(self::PROMO_TOKEN_PATTERN, $last))) {
                $count -= 2;
                continue;
            }
            break;
        }

        return $count;
    }

    /** "المصدر: …" / "Source: …" attribution line. */
    private function isSourceLine(string $line): bool
    {
        return (bool) preg_match('/^[\s\p{P}\p{S}\p{Cf}]*(?:المصدر|source)\s*:/iu', $line);
    }

    private function hasApplyPageLink(string $line): bool
    {
        if (preg_match_all('/https?:\/\/\S+/iu', $line, $urls)) {
            foreach ($urls[0] as $url) {
                if ($this->isAllowedApplyUrl($url) && $this->looksLikeApplyPage($url)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isSeparatorLine(string $line): bool
    {
        return (bool) preg_match('/^[\s\-_=—–─━•*~.·]{5,}$/u', $line);
    }

    /** Email or a phone-like digit run, in any digit script (٠٥٥…, 055…). */
    private function carriesEmailOrPhone(string $line): bool
    {
        return $this->findEmail($line) !== null || (bool) preg_match('/\p{Nd}[\p{Nd}\s\-]{6,}\p{Nd}/u', $line);
    }

    /** Email, an allowed link, or a phone-like digit run: never cut as footer. */
    private function carriesContact(string $line): bool
    {
        if ($this->carriesEmailOrPhone($line)) {
            return true;
        }
        if (preg_match_all('/https?:\/\/\S+/iu', $line, $urls)) {
            foreach ($urls[0] as $url) {
                if ($this->isAllowedApplyUrl($url)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isPromoLine(string $line): bool
    {
        // A promo opener ("تابعونا: <link>") stays promotion even with a link;
        // only an email or phone on the line keeps it.
        if (preg_match(self::PROMO_LINE_PATTERN, $line)) {
            return ! $this->carriesEmailOrPhone($line)
                && ! ($this->isSourceLine($line) && $this->hasApplyPageLink($line));
        }
        if ($this->carriesContact($line)) {
            return false;
        }

        // A line that is only links/handles (with or without a scheme) to denied hosts.
        $linkPattern = '/(?:https?:\/\/)?(?:www\.)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\/\S*)?|@\w+/iu';
        if (preg_match_all($linkPattern, $line, $links)) {
            $rest = preg_replace($linkPattern, '', $line);
            if (trim((string) preg_replace('/[\s\p{P}\p{S}\p{Cf}]+/u', '', (string) $rest)) !== '') {
                return false;
            }
            foreach ($links[0] as $link) {
                if (str_starts_with($link, '@')) {
                    continue;
                }
                $url = preg_match('/^https?:\/\//i', $link) ? $link : 'https://'.$link;
                if ($this->isAllowedApplyUrl(preg_replace('/^http:/i', 'https:', $url))) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * The post's own "how to apply" lines (e.g. "للتقديم: ارسل السيرة على
     * الواتساب ... 05xxxxxxxx"), shown as text when there is no link or email.
     * Phone numbers are never turned into an apply action. A generic contact
     * phrase ("التواصل عبر") counts only when the block holds a contact, so a
     * duty like "التواصل مع العملاء" is not mistaken for instructions.
     */
    public function applyInstructions(string $text): ?string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $this->stripPromoFooter($text)) ?: []),
            fn (string $l): bool => $l !== '',
        ));

        foreach ($lines as $i => $line) {
            $strong = preg_match('/(?:للتقديم|طريقة التقديم|آلية التقديم|التقديم عبر|التقديم من خلال|رابط التقديم|التقدم على|التقدم عبر|ارسال السيرة|إرسال السيرة|how to apply|send your (?:updated )?cv)/iu', $line);
            $weak = preg_match('/(?:التواصل عبر|التواصل على|للتواصل|التقديم على|to apply)/iu', $line);
            if (! $strong && ! $weak) {
                continue;
            }

            $block = implode("\n", array_slice($lines, $i, 3));
            if (! $strong && ! $this->carriesContact($block) && ! preg_match('/واتس|whatsapp/iu', $block)) {
                continue;
            }

            return mb_strlen($block) > 400 ? mb_substr($block, 0, 397).'...' : $block;
        }

        return null;
    }

    private function findEmail(string $text): ?string
    {
        return preg_match(self::EMAIL_PATTERN, $text, $m) ? strtolower($m[0]) : null;
    }
}
