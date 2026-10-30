<?php

namespace App\Services\Notifications;

use App\Services\I18n\ServerText;
use App\Support\DailyNotificationCandidate;
use App\Support\NotificationText;

/**
 * Notification copy as catalog labels (Abobker85/Sirati#12), so it follows the
 * reader's app language and is editable in the admin Labels page.
 */
class NotificationTemplateService
{
    /**
     * Every templated title and the bodies sent with it, most specific first.
     * Used to recognise text stored before keys were kept.
     */
    public const TEMPLATES = [
        'serverNotifyFirstAnalysisTitle' => ['serverNotifyFirstAnalysisBody'],
        'serverNotifyLowScoreTitle' => ['serverNotifyLowScoreBodyScoreTip', 'serverNotifyLowScoreBodyTip', 'serverNotifyLowScoreBodyScore', 'serverNotifyLowScoreBody'],
        'serverNotifyAnalysisToCvTitle' => ['serverNotifyAnalysisToCvBody'],
        'serverNotifyStaleCvTitle' => ['serverNotifyStaleCvBody'],
        'serverNotifyMatchingJobTitle' => ['serverNotifyMatchingJobBodyTitleCompany', 'serverNotifyMatchingJobBodyTitle', 'serverNotifyMatchingJobBody'],
        'serverNotifyEducationTitle' => ['serverNotifyEducationBodyTitle', 'serverNotifyEducationBody'],
        'serverNotifyTipTitle' => ['serverNotifyTipBody'],
        'serverNotifyCvReadyTitle' => ['serverNotifyCvReadyBody'],
        'serverNotifyCvFailedTitle' => ['serverNotifyCvFailedBody'],
        'serverNotifyNewJobTitle' => ['serverNotifyNewJobBodyLocationCompany', 'serverNotifyNewJobBodyLocation', 'serverNotifyNewJobBodyCompany', 'serverNotifyNewJobBody'],
    ];

    /**
     * @return array{title: string, body: string}
     */
    public function render(DailyNotificationCandidate $candidate, string $language): array
    {
        $text = $this->text($candidate);

        return ['title' => $text->title($language), 'body' => $text->body($language)];
    }

    public function text(DailyNotificationCandidate $candidate): NotificationText
    {
        $ctx = $candidate->context;

        return match ($candidate->templateKey) {
            'first_analysis' => new NotificationText('serverNotifyFirstAnalysisTitle', 'serverNotifyFirstAnalysisBody'),
            'low_ats_score' => $this->lowScore($ctx),
            'analysis_to_cv' => new NotificationText('serverNotifyAnalysisToCvTitle', 'serverNotifyAnalysisToCvBody'),
            'stale_cv' => new NotificationText('serverNotifyStaleCvTitle', 'serverNotifyStaleCvBody'),
            'matching_job' => $this->matchingJob($ctx),
            'relevant_education' => $this->education($ctx),
            default => new NotificationText('serverNotifyTipTitle', 'serverNotifyTipBody'),
        };
    }

    /** A new job pushed as soon as it is aggregated. No company or place is invented. */
    public function newJob(string $jobTitle, ?string $company, ?string $location): NotificationText
    {
        $params = array_filter([
            'jobTitle' => $this->safeTip($jobTitle) ?? '',
            'company' => $this->safeTip($company),
            'location' => $this->safeTip($location),
        ], fn ($value) => $value !== null);

        $body = match (true) {
            isset($params['company'], $params['location']) => 'serverNotifyNewJobBodyLocationCompany',
            isset($params['location']) => 'serverNotifyNewJobBodyLocation',
            isset($params['company']) => 'serverNotifyNewJobBodyCompany',
            default => 'serverNotifyNewJobBody',
        };

        return new NotificationText('serverNotifyNewJobTitle', $body, $params);
    }

    /**
     * Recognises a title and body sent before keys were stored, in any bundled
     * language, and recovers the values. Null when it is not templated text
     * (for example an admin broadcast).
     */
    public function recognise(string $title, string $body, ServerText $text): ?NotificationText
    {
        foreach (['ar', 'en'] as $language) {
            foreach (self::TEMPLATES as $titleKey => $bodyKeys) {
                if ($text->get($titleKey, [], $language) !== $title) {
                    continue;
                }
                foreach ($bodyKeys as $bodyKey) {
                    $params = self::match($text->get($bodyKey, [], $language), $body);
                    if ($params !== null) {
                        return new NotificationText($titleKey, $bodyKey, $params);
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string, string>|null the placeholder values when $value is $template filled in */
    private static function match(string $template, string $value): ?array
    {
        $parts = preg_split('/\{(\w+)\}/u', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        foreach ($parts as $i => $part) {
            $pattern .= $i % 2 === 0 ? preg_quote($part, '/') : '(?<'.$part.'>.+?)';
        }

        if (preg_match('/^'.$pattern.'$/su', $value, $m) !== 1) {
            return null;
        }

        return array_filter($m, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    private function lowScore(array $ctx): NotificationText
    {
        $score = $ctx['score'] ?? null;
        $params = array_filter([
            'score' => is_numeric($score) ? (string) $score : null,
            'tip' => $this->safeTip($ctx['tip'] ?? null),
        ], fn ($value) => $value !== null);

        $body = match (true) {
            isset($params['score'], $params['tip']) => 'serverNotifyLowScoreBodyScoreTip',
            isset($params['tip']) => 'serverNotifyLowScoreBodyTip',
            isset($params['score']) => 'serverNotifyLowScoreBodyScore',
            default => 'serverNotifyLowScoreBody',
        };

        return new NotificationText('serverNotifyLowScoreTitle', $body, $params);
    }

    private function matchingJob(array $ctx): NotificationText
    {
        $title = $this->safeTip($ctx['job_title'] ?? null);
        $company = $this->safeTip($ctx['company'] ?? null);

        return match (true) {
            $title !== null && $company !== null => new NotificationText('serverNotifyMatchingJobTitle', 'serverNotifyMatchingJobBodyTitleCompany', ['jobTitle' => $title, 'company' => $company]),
            $title !== null => new NotificationText('serverNotifyMatchingJobTitle', 'serverNotifyMatchingJobBodyTitle', ['jobTitle' => $title]),
            default => new NotificationText('serverNotifyMatchingJobTitle', 'serverNotifyMatchingJobBody'),
        };
    }

    private function education(array $ctx): NotificationText
    {
        $title = $this->safeTip($ctx['title'] ?? null);

        return $title !== null
            ? new NotificationText('serverNotifyEducationTitle', 'serverNotifyEducationBodyTitle', ['title' => $title])
            : new NotificationText('serverNotifyEducationTitle', 'serverNotifyEducationBody');
    }

    private function safeTip(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        // Keep push bodies compact and free of raw markup.
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        if (mb_strlen($text) > 120) {
            $text = rtrim(mb_substr($text, 0, 117)).'…';
        }

        return $text;
    }
}
