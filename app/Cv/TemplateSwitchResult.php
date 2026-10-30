<?php

namespace App\Cv;

use App\Services\I18n\ServerText;

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

        $keys = [
            'summary' => 'serverCvSectionSummary',
            'skills' => 'serverCvSectionSkills',
            'experience' => 'serverCvSectionExperience',
            'education' => 'serverCvSectionEducation',
            'certifications' => 'serverCvSectionCertifications',
            'projects' => 'serverCvSectionProjects',
            'languages' => 'serverCvSectionLanguages',
            'custom_sections' => 'serverCvSectionCustom',
        ];

        $names = array_map(
            fn (string $key) => isset($keys[$key]) ? ServerText::t($keys[$key], [], $language) : $key,
            $this->omittedSections,
        );

        return ServerText::t('serverCvTemplateHidesSections', [
            'sections' => implode(ServerText::t('serverCvTemplateSectionListSeparator', [], $language), $names),
        ], $language);
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
