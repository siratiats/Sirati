<?php

namespace Tests\Feature;

use App\Models\JobNews;
use App\Services\Jobs\ApplyLinkExtractor;
use App\Services\Jobs\SaudiJobAggregatorService;
use Database\Seeders\JobTitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Apply destinations for aggregated jobs: never Telegram, social pages,
 * shorteners or hashtag searches; an employer link, an email, or nothing.
 * Inputs are generated (seeded) so the rules hold beyond the cited posts.
 */
class JobApplyLinksTest extends TestCase
{
    use RefreshDatabase;

    private const DENIED_HOSTS = [
        't.me', 'telegram.me', 'telegram.org', 'telesco.pe', 'instagram.com', 'snapchat.com',
        'x.com', 'twitter.com', 'facebook.com', 'tiktok.com', 'youtube.com', 'youtu.be',
        'chat.whatsapp.com', 'wa.me', 'bit.ly', 'lik.ad', 't.co', 'tinyurl.com', 'linktr.ee',
    ];

    private const ALLOWED = [
        'https://jadarat.sa/ExploreJobs?JobTab=4',
        'https://careers.example-co.com/jobs/123',
        'https://www.taqeem.gov.sa/careers',
        'https://forms.gle/AbCdEf123',
        'https://www.linkedin.com/jobs/view/4012345678',
        'https://boards.greenhouse.io/acme/jobs/55',
    ];

    private ApplyLinkExtractor $links;

    protected function setUp(): void
    {
        parent::setUp();
        $this->links = new ApplyLinkExtractor();
        mt_srand(20261029);
    }

    private function deniedUrl(): string
    {
        $host = self::DENIED_HOSTS[array_rand(self::DENIED_HOSTS)];
        $sub = mt_rand(0, 2) === 0 ? 'www.' : (mt_rand(0, 3) === 0 ? 'm.' : '');
        $path = ['/channel', '/joinchat/Abc', '/s/jobs', '/add/user', '/p/xyz', ''][mt_rand(0, 5)];

        return 'https://'.$sub.$host.$path;
    }

    public function test_denied_hosts_and_their_subdomains_are_never_apply_links(): void
    {
        foreach (self::DENIED_HOSTS as $host) {
            foreach (['', 'www.', 'm.', 'a.b.'] as $sub) {
                foreach (['', '/x', '/jobs/view/1', '/?apply=1'] as $path) {
                    $this->assertFalse($this->links->isAllowedApplyUrl("https://{$sub}{$host}{$path}"), "{$sub}{$host}{$path}");
                }
            }
        }
    }

    public function test_unsafe_url_shapes_are_rejected(): void
    {
        foreach ([
            'http://careers.example.com/jobs',          // not https
            'https://10.0.0.5/jobs',                     // IP host
            'https://[::1]/jobs',
            'https://user:pass@careers.example.com/',    // credentials
            'https://careers.example.com:8443/jobs',     // explicit port
            'https://t.me.evil.example/',                // fine host, kept below as allowed control
            'javascript:alert(1)',
            'mailto:hr@example.com',
            'https://localhost/jobs',
            'https://example.com/?q=%23وظائف',            // hashtag search
            'https://www.linkedin.com/in/someone',       // profile, not a job
            'https://www.linkedin.com/posts/abc',
        ] as $url) {
            $expected = $url === 'https://t.me.evil.example/';
            $this->assertSame($expected, $this->links->isAllowedApplyUrl($url), $url);
        }
    }

    public function test_the_only_allowed_link_wins_regardless_of_position_among_denied_ones(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $allowed = self::ALLOWED[array_rand(self::ALLOWED)];
            $anchors = array_map(fn () => $this->deniedUrl(), range(1, mt_rand(0, 6)));
            array_splice($anchors, mt_rand(0, count($anchors)), 0, [$allowed]);

            $html = 'نص الإعلان<br/>'.implode('<br/>', array_map(fn (string $u) => '<a href="'.$u.'">link</a>', $anchors));
            $result = $this->links->fromMessageHtml($html);

            $this->assertSame(ApplyLinkExtractor::METHOD_URL, $result['method']);
            $this->assertSame($allowed, $result['url']);
        }
    }

    public function test_posts_with_only_denied_links_fall_back_to_email_or_none(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $links = implode(' ', array_map(fn () => '<a href="'.$this->deniedUrl().'">x</a>', range(1, mt_rand(1, 5))));
            $withEmail = mt_rand(0, 1) === 1;
            $html = 'مطلوب محاسب<br/>المتطلبات: خبرة سنتين '.$links.'<br/>'
                .($withEmail ? "للتقديم: Jobs.HR{$i}@Company-{$i}.com.sa<br/>" : '').'تابعونا على انستقرام';

            $result = $this->links->fromMessageHtml($html);

            $this->assertNull($result['url']);
            if ($withEmail) {
                $this->assertSame(ApplyLinkExtractor::METHOD_EMAIL, $result['method']);
                $this->assertSame(strtolower("jobs.hr{$i}@company-{$i}.com.sa"), $result['email']);
            } else {
                $this->assertSame(ApplyLinkExtractor::METHOD_NONE, $result['method']);
            }
        }
    }

    public function test_footer_stripping_is_idempotent_and_keeps_contact_lines(): void
    {
        $footers = [
            "‏🌀 instagram.com/ksa4jobs",
            "‏👻http://www.snapchat.com/add/channel",
            'كود خصم ايهرب : GKZ9759',
            'رابط الانضمام لرسائل الواتس اب',
            '‏https://chat.whatsapp.com/JyZpldEfLhlHsJZ1cNx8Hf',
            '⭕ ان لم تكن بحاجة الإعلان.. انشره واكسب اجر من هم بحاجته 🌹',
            'المصدر: https://x.com/SomeOrg/status/1',
            'تابعونا على تويتر @channel',
            '--------------------------------------',
        ];
        $contacts = [
            'للتقديم: hr@example.com',
            '✅ رابط التقديم: https://jadarat.sa/ExploreJobs',
            'التواصل عبر الواتس اب 0537422024',
        ];

        for ($i = 0; $i < 150; $i++) {
            $contact = $contacts[array_rand($contacts)];
            $tail = array_map(fn () => $footers[array_rand($footers)], range(0, mt_rand(0, 5)));
            $body = "مطلوب فني صيانة في الرياض\nخبرة 3 سنوات\n{$contact}\n".implode("\n", $tail);

            $once = $this->links->stripPromoFooter($body);

            $this->assertSame($once, $this->links->stripPromoFooter($once), 'not idempotent');
            $this->assertStringContainsString($contact, $once);
            $this->assertStringNotContainsStringIgnoringCase('instagram.com', $once);
            $this->assertStringNotContainsStringIgnoringCase('whatsapp.com', $once);
            $this->assertStringNotContainsString('كود خصم', $once);
        }
    }

    public function test_short_signature_below_a_separator_is_removed(): void
    {
        $body = "وظائف في جدة\nسعودي الجنسية\nللتواصل 0561027678\n--------------------------------------\n🔻وظائف السعودية";

        $this->assertSame("وظائف في جدة\nسعودي الجنسية\nللتواصل 0561027678", $this->links->stripPromoFooter($body));
    }

    public function test_fingerprint_ignores_spelling_noise_but_not_place_or_employer(): void
    {
        $base = JobNews::fingerprintFor('مطلوب مهندس مدني للعمل في مشروع', 'شركة البناء', 'riyadh');

        foreach ([
            ['مَطْلُوبْ مُهَنْدِس مدنى للعمل فى مشروع!!', 'شركة  البناء', 'riyadh'],
            ['مطلـــوب مهندس مدني للعمل في مشروع 🔥', 'شركه البناء', 'RIYADH'],
        ] as [$title, $company, $city]) {
            $variant = JobNews::fingerprintFor($title, $company, $city);
            // "في" vs "فى" and "مدني" vs "مدنى" fold together; city case too.
            $this->assertSame($base, $variant, $title);
        }

        $this->assertNotSame($base, JobNews::fingerprintFor('مطلوب مهندس مدني للعمل في مشروع', 'شركة البناء', 'jeddah'));
        $this->assertNotSame($base, JobNews::fingerprintFor('مطلوب مهندس مدني للعمل في مشروع', 'مجموعة أخرى', 'riyadh'));
        $this->assertNull(JobNews::fingerprintFor('محاسب', null, null), 'short titles must not merge distinct vacancies');

        // Without an employer only a shared destination identifies a vacancy.
        $title = 'مطلوب مهندس كهرباء للعمل في الدمام';
        $this->assertNull(JobNews::fingerprintFor($title, null, 'dammam'));
        $this->assertNull(JobNews::fingerprintFor($title, '  ', 'dammam'));
        $this->assertSame(
            JobNews::fingerprintFor($title, null, 'dammam', 'https://a.example/jobs/1'),
            JobNews::fingerprintFor($title, null, 'dammam', 'HTTPS://A.EXAMPLE/jobs/1'),
        );
        $this->assertNotSame(
            JobNews::fingerprintFor($title, null, 'dammam', 'https://a.example/jobs/1'),
            JobNews::fingerprintFor($title, null, 'dammam', 'https://b.example/jobs/2'),
        );
    }

    public function test_a_cued_apply_link_beats_an_earlier_homepage_and_bare_unknown_hosts_never_apply(): void
    {
        $homepages = ['https://www.herfy.com/', 'https://acme-group.example/about', 'https://news.example.sa/article/5'];
        $cues = ['✅ رابط التقديم:', 'للتقديم:', 'Apply here:', 'التسجيل عبر'];

        for ($i = 0; $i < 120; $i++) {
            $home = $homepages[array_rand($homepages)];
            $apply = 'https://portal'.$i.'.example.sa/x/'.$i;
            $lines = [
                'تعلن الشركة (<a href="'.$home.'">الموقع</a>) عن وظائف',
                'المتطلبات: خبرة سنتين',
            ];
            array_splice($lines, mt_rand(1, 2), 0, [$cues[array_rand($cues)].' <a href="'.$apply.'">'.$apply.'</a>']);

            $this->assertSame($apply, $this->links->fromMessageHtml(implode('<br/>', $lines))['url']);

            // Without the cue line, a homepage alone is not an application page.
            $this->assertSame(
                ApplyLinkExtractor::METHOD_NONE,
                $this->links->fromMessageHtml('تعلن الشركة (<a href="'.$home.'">الموقع</a>) عن وظائف<br/>المتطلبات: خبرة')['method'],
            );
        }
    }

    public function test_links_in_the_stripped_footer_never_win_over_the_body(): void
    {
        $html = 'مطلوب محاسب في الرياض<br/>للتقديم: hr@employer.sa<br/>'
            .'تابعونا: <a href="https://channel.example/careers/newsletter">النشرة</a><br/>'
            .'<a href="https://instagram.com/chan">instagram.com/chan</a>';

        $result = $this->links->fromMessageHtml($html);

        $this->assertSame(ApplyLinkExtractor::METHOD_EMAIL, $result['method']);
        $this->assertSame('hr@employer.sa', $result['email']);
    }

    public function test_cue_words_and_apply_paths_match_whole_words_and_segments(): void
    {
        foreach ([
            'على المتقدمين مراجعة الشروط <a href="https://company.example/about">https://company.example/about</a>',
            'اقرأ المقال <a href="https://news.example/blog/job-market-2026">هنا</a>',
            'تابعونا: <a href="https://channel.example/careers/newsletter">https://channel.example/careers/newsletter</a>',
        ] as $line) {
            $this->assertSame(ApplyLinkExtractor::METHOD_NONE,
                $this->links->fromMessageHtml('مطلوب محاسب<br/>'.$line)['method'], $line);
        }

        foreach ([
            'وللتقديم <a href="https://portal.example.sa/x">هنا</a>' => 'https://portal.example.sa/x',
            'Apply: <a href="https://portal.example.sa/y">here</a>' => 'https://portal.example.sa/y',
            'المصدر: <a href="https://careers.bahri.sa">https://careers.bahri.sa</a>' => 'https://careers.bahri.sa',
        ] as $line => $expected) {
            $this->assertSame($expected, $this->links->fromMessageHtml('مطلوب محاسب<br/>'.$line)['url'], $line);
        }
    }

    public function test_shortener_shaped_links_are_not_apply_but_real_careers_pages_are(): void
    {
        foreach (['https://dlvr.it/Ab12', 'https://xyz.co/k9F2x', 'https://www.sho.rt/AbC123', 'https://ab.cd/q1w2e3/',
            'https://dlvr.it/Ab12?utm_source=tg', 'https://dlvr.it/Ab12Cd34Ef56Gh7'] as $url) {
            $this->assertFalse($this->links->isAllowedApplyUrl($url), $url);
        }
        foreach ([
            'https://qiwa.sa/login', 'https://hr.sabb.sa/Ab3k', 'https://acme.sa/about', 'https://acme.sa/Careers',
            'https://herfy.com/careers', 'https://www.uap.sa/', 'https://jadarat.sa/ExploreJobs',
            'https://forms.gle/2HHVtUTWwp8xhM2i9', 'https://acme.sa/jobs/12', 'https://careers.acme-holding.com/ar/list',
        ] as $url) {
            $this->assertTrue($this->links->isAllowedApplyUrl($url), $url);
        }
    }

    public function test_destination_identity_ignores_noise_but_not_path_case(): void
    {
        $base = JobNews::normalizeDestination('https://careers.example.com/jobs/ABC');
        foreach ([
            'HTTPS://WWW.Careers.Example.com/jobs/ABC',
            'https://careers.example.com/jobs/ABC/',
            'https://careers.example.com/jobs/ABC?utm_source=chan&utm_medium=tg',
            'https://careers.example.com/jobs/ABC?fbclid=1',
        ] as $variant) {
            $this->assertSame($base, JobNews::normalizeDestination($variant), $variant);
        }
        $this->assertNotSame($base, JobNews::normalizeDestination('https://careers.example.com/jobs/abc'));
        $this->assertNotSame($base, JobNews::normalizeDestination('https://careers.example.com/jobs/ABC?req=2'));
        // Distinct vacancy identities stay distinct.
        foreach ([
            ['https://careers.example.com/#/jobs/101', 'https://careers.example.com/#/jobs/202'],
            ['https://careers.example.com/apply?req.id=101', 'https://careers.example.com/apply?req_id=101'],
            ['https://careers.example.com/apply?a=1&b=2', 'https://careers.example.com/apply?b=2&a=1'],
            ['https://careers.example.com/apply?id=1&id=2', 'https://careers.example.com/apply?id=1'],
        ] as [$one, $two]) {
            $this->assertNotSame(JobNews::normalizeDestination($one), JobNews::normalizeDestination($two), "$one vs $two");
        }
        $this->assertSame('hr@x.sa', JobNews::normalizeDestination(' HR@X.sa '));
        $this->assertNull(JobNews::normalizeDestination('  '));
    }

    public function test_phone_lines_in_any_digit_script_survive_the_footer_cut(): void
    {
        foreach (['للتواصل واتساب: ٠٥٥١٢٣٤٥٦٧', 'واتساب: 0551234567', 'واتس اب ۰۵۵۱۲۳۴۵۶۷'] as $line) {
            $this->assertStringContainsString($line, $this->links->stripPromoFooter("مطلوب سائق\n".$line), $line);
        }
    }

    public function test_link_less_refetch_keeps_identity_so_reposts_still_merge(): void
    {
        $this->seed(JobTitleSeeder::class);
        $title = 'مطلوب مهندس مدني للعمل فورا في الرياض';
        $link = '<br/>للتقديم <a href="https://portal.example.sa/jobs/77">هنا</a>';

        $this->ingest('one', $this->telegramPage('one', 1, $title.$link));
        $this->ingest('one', $this->telegramPage('one', 1, $title));
        $first = JobNews::where('external_id', '1')->firstOrFail();
        $this->assertNotNull($first->fingerprint);
        $this->assertSame('https://portal.example.sa/jobs/77', $first->apply_url);

        $this->ingest('two', $this->telegramPage('two', 2, $title.str_replace('/77', '/77?utm_source=two', $link)));
        $this->assertSame(1, JobNews::query()->where('title', 'like', '%مهندس مدني%')->count());
    }

    public function test_footer_cut_keeps_real_lines_and_contacts(): void
    {
        $realTails = [
            ['-----', 'الرياض', 'دوام كامل'],
            ['-----', 'خبرة سنتين', 'يشترط لغة إنجليزية', 'الراتب مجزي'],
            ['انضم إلى فريقنا المتميز في الرياض'],
            ['شارك في تطوير منتجاتنا الرقمية'],
            ['واتساب: 0551234567'],
            ['للتواصل واتساب 055 123 4567'],
            ['قناة توزيع: التجزئة والجملة'],
        ];

        foreach ($realTails as $tail) {
            $body = "مطلوب مهندس مدني للعمل في المشروع
".implode("
", $tail);
            $kept = $this->links->stripPromoFooter($body);
            foreach ($tail as $line) {
                if (trim($line, '-') === '') {
                    continue;
                }
                $this->assertStringContainsString($line, $kept, implode(' | ', $tail));
            }
        }
    }

    public function test_apply_instructions_need_an_apply_phrase_or_a_contact(): void
    {
        foreach ([
            "من مهام الوظيفة:
التواصل مع العملاء ومتابعة الطلبات
إعداد التقارير",
            "التقديم على الوظائف يتطلب خبرة خمس سنوات
إجادة الإنجليزية
رخصة قيادة",
            "We want someone able to apply best practices
Team player",
        ] as $body) {
            $this->assertNull($this->links->applyInstructions($body), $body);
        }

        foreach ([
            "سعودي الجنسية
لمن لديه الرغبة التواصل عبر الواتس اب
0537422024" => '0537422024',
            "المتطلبات: خبرة
للتقديم: ارسال السيرة الذاتية على الواتساب" => 'للتقديم',
            "Requirements: SQL
How to Apply: send your CV with the subject line" => 'How to Apply',
        ] as $body => $expected) {
            $this->assertStringContainsString($expected, (string) $this->links->applyInstructions($body), $body);
        }
    }

    private function telegramPage(string $channel, int $postId, string $messageHtml): string
    {
        // The wrapper carries the channel's own avatar link, as on t.me/s/<channel>.
        return <<<HTML
<html><body>
<div class="tgme_widget_message_wrap js-widget_message_wrap">
  <div class="tgme_widget_message js-widget_message" data-post="{$channel}/{$postId}">
    <a class="tgme_widget_message_user_photo" href="https://t.me/{$channel}"><img src="https://cdn4.telesco.pe/file/a.jpg"></a>
    <div class="tgme_widget_message_text js-message_text">{$messageHtml}</div>
    <time datetime="2026-10-28T12:00:00+03:00"></time>
  </div>
</div>
</body></html>
HTML;
    }

    private function ingest(string $channel, string $html): array
    {
        Http::fake(["https://t.me/s/{$channel}*" => Http::response($html, 200)]);

        return app(SaudiJobAggregatorService::class)->aggregateFeed([
            'source' => "telegram_{$channel}",
            'name' => "@{$channel}",
            'channel' => $channel,
            'type' => 'telegram',
            'language' => 'ar',
        ]);
    }

    public function test_telegram_ingest_stores_employer_link_and_strips_footer(): void
    {
        $this->seed(JobTitleSeeder::class);

        $this->ingest('chan', $this->telegramPage('chan', 77, implode('<br/>', [
            'مطلوب مطور برمجيات للعمل في الرياض',
            'خبرة في لارفيل',
            '✅ رابط التقديم: <a href="https://careers.example-co.com/jobs/9">https://careers.example-co.com/jobs/9</a>',
            '‏🌀 <a href="https://instagram.com/chan">instagram.com/chan</a>',
            '‏<a href="https://chat.whatsapp.com/Abc">https://chat.whatsapp.com/Abc</a>',
        ])));

        $job = JobNews::where('external_id', '77')->firstOrFail();
        $this->assertSame('https://careers.example-co.com/jobs/9', $job->apply_url);
        $this->assertSame('https://t.me/chan/77', $job->url, 'Telegram post stays as the source only');
        $this->assertStringNotContainsString('instagram', $job->body);
        $this->assertNull($job->company, 'no invented employer');
        $this->assertNotNull($job->fingerprint);
    }

    public function test_telegram_post_without_destination_has_no_apply_link_and_refetch_keeps_a_good_one(): void
    {
        $this->seed(JobTitleSeeder::class);

        $this->ingest('chan', $this->telegramPage('chan', 80, 'مطلوب كاشير لمطعم في جدة<br/>التواصل عبر الواتس 0551234567'));
        $job = JobNews::where('external_id', '80')->firstOrFail();
        $this->assertNull($job->apply_url);
        $this->assertNull($job->apply_email);

        // Same post later shows a link, then loses it again on another fetch.
        $job->forceFill(['apply_url' => 'https://careers.example-co.com/jobs/80'])->save();
        $this->ingest('chan', $this->telegramPage('chan', 80, 'مطلوب كاشير لمطعم في جدة<br/>التواصل عبر الواتس 0551234567'));
        $this->assertSame('https://careers.example-co.com/jobs/80', $job->fresh()->apply_url);
    }

    public function test_dedupe_never_merges_unnamed_employers_or_conflicting_destinations(): void
    {
        $this->seed(JobTitleSeeder::class);

        // Same headline, no employer, no destination: may be different jobs.
        $this->ingest('one', $this->telegramPage('one', 1, 'مطلوب مهندس مدني للعمل فورا في الرياض<br/>خبرة سنتين'));
        $this->ingest('two', $this->telegramPage('two', 2, 'مطلوب مهندس مدني للعمل فورا في الرياض<br/>خبرة سنتين'));
        $this->assertSame(2, JobNews::query()->where('title', 'like', '%مهندس مدني%')->count());

        // Same employer and headline but different requisitions.
        $text = 'شركة الأفق تعلن عن وظيفة مهندس كهرباء في الدمام<br/>للتقديم ';
        $this->ingest('three', $this->telegramPage('three', 3, $text.'<a href="https://careers.alofoq.example/jobs/101">هنا</a>'));
        $this->ingest('four', $this->telegramPage('four', 4, $text.'<a href="https://careers.alofoq.example/jobs/202">هنا</a>'));
        $this->assertEqualsCanonicalizing(
            ['https://careers.alofoq.example/jobs/101', 'https://careers.alofoq.example/jobs/202'],
            JobNews::query()->where('title', 'like', '%مهندس كهرباء%')->pluck('apply_url')->all(),
        );
    }

    public function test_refetch_with_only_an_email_keeps_the_stored_link(): void
    {
        $this->seed(JobTitleSeeder::class);
        $page = fn (string $apply) => $this->telegramPage('chan', 90, 'مطلوب محاسب في جدة<br/>'.$apply);

        $this->ingest('chan', $page('للتقديم: <a href="https://careers.example-co.com/jobs/90">الرابط</a>'));
        $this->ingest('chan', $page('للتقديم: hr@example-co.com'));

        $job = JobNews::where('external_id', '90')->firstOrFail();
        $this->assertSame('https://careers.example-co.com/jobs/90', $job->apply_url);
    }

    public function test_same_vacancy_from_two_channels_is_stored_once_and_gains_the_link(): void
    {
        $this->seed(JobTitleSeeder::class);
        $text = 'شركة الأفق تعلن عن وظيفة مهندس كهرباء في الدمام<br/>خبرة 5 سنوات';

        $this->ingest('first', $this->telegramPage('first', 1, $text));
        $this->ingest('second', $this->telegramPage('second', 2, $text.'<br/>للتقديم <a href="https://careers.alofoq.example/jobs/5">هنا</a>'));

        $rows = JobNews::query()->where('title', 'like', '%مهندس كهرباء%')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('https://careers.alofoq.example/jobs/5', $rows->first()->apply_url);
    }

    public function test_api_never_offers_a_denied_stored_link_and_exposes_no_score(): void
    {
        $make = fn (array $extra) => JobNews::create(array_merge([
            'language' => 'ar',
            'title' => 'وظيفة '.uniqid(),
            'body' => 'تفاصيل',
            'is_published' => true,
            'published_at' => now(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'external_source' => 'telegram_x',
            'external_id' => uniqid(),
        ], $extra));

        for ($i = 0; $i < 25; $i++) {
            $make(['apply_url' => $this->deniedUrl()]);
        }
        $make(['apply_url' => 'https://t.me/chan/5', 'apply_email' => 'HR@Example.com']);
        $curated = $make(['apply_url' => 'https://jadarat.sa/x', 'external_source' => null, 'external_id' => null]);

        $items = collect($this->getJson('/api/mobile/job-news')->assertOk()->json('data.items'));

        foreach ($items as $item) {
            foreach (['match_score', 'match_reasons', 'matched_skills', 'missing_skills'] as $removed) {
                $this->assertArrayNotHasKey($removed, $item);
            }
            $this->assertContains($item['apply_method'], ['url', 'email', 'none']);
            if ($item['apply_url'] !== null) {
                $this->assertTrue($this->links->isAllowedApplyUrl($item['apply_url']), $item['apply_url']);
            }
            $this->assertSame($item['apply_method'] === 'url', $item['apply_url'] !== null);
            $this->assertSame($item['apply_method'] === 'email', $item['apply_email'] !== null);
        }

        $this->assertSame('hr@example.com', $items->firstWhere('apply_email', '!=', null)['apply_email']);
        $curatedItem = $items->firstWhere('id', $curated->id);
        $this->assertSame('url', $curatedItem['apply_method']);
        $this->assertNotNull($curatedItem['valid_until_label'], 'curated rows keep their deadline');
        $this->assertTrue($items->where('id', '!=', $curated->id)->every(fn ($i) => $i['valid_until_label'] === null),
            'aggregated rows have no stated deadline to show');
    }

    public function test_repair_command_cleans_legacy_rows_and_is_idempotent(): void
    {
        $legacy = JobNews::create([
            'language' => 'ar',
            'title' => 'مطلوب أخصائي موارد بشرية في الرياض',
            'company' => 'جهة سعودية معتمدة',
            'body' => "التفاصيل\nارسل السيرة إلى cv@corp.example.sa\n‏🌀 instagram.com/ksa4jobs",
            'apply_url' => 'https://t.me/jobmag/123',
            'url' => 'https://t.me/jobmag/123',
            'is_published' => true,
            'published_at' => now(),
            'external_source' => 'telegram_jobmag',
            'external_id' => '123',
        ]);

        $this->artisan('jobs:repair-listings')->assertSuccessful();
        $legacy->refresh();

        $this->assertNull($legacy->apply_url);
        $this->assertSame('cv@corp.example.sa', $legacy->apply_email);
        $this->assertNull($legacy->company);
        $this->assertStringNotContainsString('instagram', $legacy->body);
        $this->assertSame('https://t.me/jobmag/123', $legacy->url);

        $this->artisan('jobs:repair-listings')->expectsOutput('Repaired 0 job listing(s).')->assertSuccessful();
    }

    public function test_region_filter_returns_only_cities_in_that_region(): void
    {
        foreach (['riyadh', 'qassim', 'dammam', 'jeddah', 'tabuk', 'abha', null] as $city) {
            JobNews::create([
                'language' => 'ar', 'title' => "وظيفة {$city}", 'body' => 'x', 'city' => $city,
                'is_published' => true, 'published_at' => now(),
            ]);
        }

        $cities = collect($this->getJson('/api/mobile/job-news?region=central')->assertOk()->json('data.items'))
            ->pluck('city')->unique()->sort()->values()->all();
        $this->assertSame(['qassim', 'riyadh'], $cities);

        $this->getJson('/api/mobile/job-news?region=unknown')->assertOk()->assertJsonCount(7, 'data.items');
    }
}
