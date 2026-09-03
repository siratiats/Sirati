<?php

namespace App\Cv;

use App\Models\CvTemplate;

/**
 * Switching templates never mutates CV content (SIRATI-47).
 *
 * The document is the source of truth. Templates only decide what to display.
 * Sections a layout cannot show stay on the document so the switch is reversible.
 */
final class TemplateSwitch
{
    /**
     * Canonical section => template-declared aliases.
     *
     * @var array<string, list<string>>
     */
    private const ALIASES = [
        'summary' => ['summary', 'career_objective', 'executive_profile'],
        'skills' => ['skills', 'technical_skills', 'core_competencies', 'tools'],
        'experience' => ['experience', 'leadership_experience'],
        'education' => ['education'],
        'certifications' => ['certifications'],
        'projects' => ['projects', 'open_source', 'internships'],
        'languages' => ['languages'],
        'custom_sections' => [
            'custom_sections',
            'achievements',
            'board_roles',
            'activities',
            'volunteering',
            'revenue_highlights',
        ],
    ];

    public function evaluate(CvDocument $document, CvTemplate $template): TemplateSwitchResult
    {
        $supported = array_values(array_filter(
            $template->supported_sections ?: [],
            fn (mixed $section): bool => is_string($section) && $section !== '',
        ));

        $omitted = [];
        foreach ($document->populatedSectionKeys() as $section) {
            if ($supported !== [] && ! $this->supports($supported, $section)) {
                $omitted[] = $section;
            }
        }

        return new TemplateSwitchResult(
            document: $document,
            templateSlug: (string) $template->slug,
            omittedSections: $omitted,
        );
    }

    /**
     * @param  list<string>  $supported
     */
    private function supports(array $supported, string $canonical): bool
    {
        $aliases = self::ALIASES[$canonical] ?? [$canonical];

        return array_intersect($supported, $aliases) !== [];
    }
}
