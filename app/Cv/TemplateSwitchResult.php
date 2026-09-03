<?php

namespace App\Cv;

final readonly class TemplateSwitchResult
{
    /**
     * @param  list<string>  $omittedSections
     */
    public function __construct(
        public CvDocument $document,
        public string $templateSlug,
        public array $omittedSections,
    ) {}

    public function hasWarning(): bool
    {
        return $this->omittedSections !== [];
    }

    public function warningMessage(string $language = 'en'): ?string
    {
        if (! $this->hasWarning()) {
            return null;
        }

        $labels = $language === 'ar'
            ? [
                'summary' => 'الملخص',
                'skills' => 'المهارات',
                'experience' => 'الخبرات',
                'education' => 'التعليم',
                'certifications' => 'الشهادات',
                'projects' => 'المشاريع',
                'languages' => 'اللغات',
                'custom_sections' => 'أقسام إضافية',
            ]
            : [
                'summary' => 'summary',
                'skills' => 'skills',
                'experience' => 'experience',
                'education' => 'education',
                'certifications' => 'certifications',
                'projects' => 'projects',
                'languages' => 'languages',
                'custom_sections' => 'extra sections',
            ];

        $names = array_map(
            fn (string $key) => $labels[$key] ?? $key,
            $this->omittedSections,
        );

        if ($language === 'ar') {
            return 'هذا القالب لن يعرض: '.implode('، ', $names).'. المحتوى محفوظ ويمكنك الرجوع للقالب السابق.';
        }

        return 'This template will hide: '.implode(', ', $names).'. Your content is kept and the switch is reversible.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $language = 'en'): array
    {
        return [
            'template' => $this->templateSlug,
            'omitted_sections' => $this->omittedSections,
            'warning' => $this->warningMessage($language),
            'document' => $this->document->toArray(),
        ];
    }
}
