<?php

namespace App\Services\Notifications;

use App\Support\DailyNotificationCandidate;

class NotificationTemplateService
{
    /**
     * @return array{title: string, body: string}
     */
    public function render(DailyNotificationCandidate $candidate, string $language): array
    {
        $en = $language === 'en';
        $ctx = $candidate->context;

        return match ($candidate->templateKey) {
            'first_analysis' => [
                'title' => $en ? 'Discover what may be weakening your CV' : 'اكتشف ما قد يضعف سيرتك',
                'body' => $en
                    ? 'Run a quick ATS analysis and get clear improvement tips in minutes.'
                    : 'حلّل سيرتك بسرعة واحصل على نصائح تحسين واضحة خلال دقائق.',
            ],
            'low_ats_score' => [
                'title' => $en
                    ? 'Your ATS score can improve'
                    : 'درجة ATS الخاصة بك قابلة للتحسين',
                'body' => $en
                    ? $this->lowScoreBodyEn($ctx)
                    : $this->lowScoreBodyAr($ctx),
            ],
            'analysis_to_cv' => [
                'title' => $en ? 'Turn analysis into an improved CV' : 'حوّل التحليل إلى سيرة محسّنة',
                'body' => $en
                    ? 'You already have analysis insights — generate a stronger CV from them now.'
                    : 'لديك نتائج تحليل جاهزة — أنشئ سيرة أقوى منها الآن.',
            ],
            'stale_cv' => [
                'title' => $en ? 'Review your CV before applying' : 'راجع سيرتك قبل التقديم',
                'body' => $en
                    ? 'Your last generated CV is over a week old. A quick refresh can improve your chances.'
                    : 'آخر سيرة أنشأتها مرّ عليها أكثر من أسبوع. تحديث سريع قد يحسّن فرصك.',
            ],
            'matching_job' => [
                'title' => $en ? 'A matching opportunity for you' : 'فرصة مناسبة لك',
                'body' => $en
                    ? $this->jobBodyEn($ctx)
                    : $this->jobBodyAr($ctx),
            ],
            'relevant_education' => [
                'title' => $en ? 'Learn something that helps your role' : 'تعلّم ما يدعم مسارك',
                'body' => $en
                    ? $this->educationBodyEn($ctx)
                    : $this->educationBodyAr($ctx),
            ],
            default => [
                'title' => $en ? 'A small tip for your job search' : 'نصيحة سريعة لبحثك عن عمل',
                'body' => $en
                    ? 'Keep your CV keywords aligned with the target job title for better ATS results.'
                    : 'حافظ على تطابق كلمات سيرتك مع المسمى المستهدف لنتائج ATS أفضل.',
            ],
        };
    }

    private function lowScoreBodyEn(array $ctx): string
    {
        $score = $ctx['score'] ?? null;
        $tip = $this->safeTip($ctx['tip'] ?? null);
        $scoreText = is_numeric($score) ? " (score: {$score})" : '';
        if ($tip !== null) {
            return "Your latest analysis{$scoreText} has a clear next step: {$tip}";
        }

        return "Your latest analysis{$scoreText} has room to grow. Open it for practical fixes.";
    }

    private function lowScoreBodyAr(array $ctx): string
    {
        $score = $ctx['score'] ?? null;
        $tip = $this->safeTip($ctx['tip'] ?? null);
        $scoreText = is_numeric($score) ? " (الدرجة: {$score})" : '';
        if ($tip !== null) {
            return "تحليلُك الأخير{$scoreText} يحتوي خطوة واضحة: {$tip}";
        }

        return "تحليلُك الأخير{$scoreText} قابل للتحسين. افتحه لترى إصلاحات عملية.";
    }

    private function jobBodyEn(array $ctx): string
    {
        $title = $this->safeTip($ctx['job_title'] ?? null);
        $company = $this->safeTip($ctx['company'] ?? null);
        if ($title && $company) {
            return "New listing related to {$title} at {$company}. Open Job News to review it.";
        }
        if ($title) {
            return "A new listing related to {$title} is available in Job News.";
        }

        return 'A new job listing may match your profile. Check Job News.';
    }

    private function jobBodyAr(array $ctx): string
    {
        $title = $this->safeTip($ctx['job_title'] ?? null);
        $company = $this->safeTip($ctx['company'] ?? null);
        if ($title && $company) {
            return "إعلان جديد مرتبط بـ {$title} لدى {$company}. افتح أخبار الوظائف لمراجعته.";
        }
        if ($title) {
            return "يتوفر إعلان جديد مرتبط بـ {$title} في أخبار الوظائف.";
        }

        return 'يوجد إعلان وظيفي قد يناسب ملفك. تصفّح أخبار الوظائف.';
    }

    private function educationBodyEn(array $ctx): string
    {
        $title = $this->safeTip($ctx['title'] ?? null);
        if ($title) {
            return "Recommended read: {$title}";
        }

        return 'A short learning piece can strengthen your next application.';
    }

    private function educationBodyAr(array $ctx): string
    {
        $title = $this->safeTip($ctx['title'] ?? null);
        if ($title) {
            return "قراءة موصى بها: {$title}";
        }

        return 'محتوى تعليمي قصير قد يقوّي طلبك التالي.';
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
