<?php

namespace App\Cv;

use JsonSerializable;

/**
 * Canonical CV document schema consumed by ATS scoring and template rendering.
 *
 * Schema version 1 stores bilingual user-authored text as `{ar, en}` objects.
 * Invariant contact values (email, phone, LinkedIn, URLs, dates) stay scalar.
 *
 * Sections may be omitted (`null`) or present-but-empty (`[]`).
 *
 * @phpstan-type DocumentArray array{
 *     schema_version: int,
 *     export_language: string,
 *     personal: array<string, mixed>,
 *     summary: array{ar: string, en: string},
 *     experience?: list<array<string, mixed>>,
 *     education?: list<array<string, mixed>>,
 *     skills?: list<array<string, mixed>>,
 *     languages?: list<array<string, mixed>>,
 *     certifications?: list<array<string, mixed>>,
 *     projects?: list<array<string, mixed>>,
 *     custom_sections?: list<array<string, mixed>>
 * }
 */
final readonly class CvDocument implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param  list<ExperienceEntry>|null  $experience
     * @param  list<EducationEntry>|null  $education
     * @param  list<Skill>|null  $skills
     * @param  list<LanguageSkill>|null  $languages
     * @param  list<Certification>|null  $certifications
     * @param  list<Project>|null  $projects
     * @param  list<CustomSection>|null  $customSections
     */
    public function __construct(
        public int $schemaVersion = self::SCHEMA_VERSION,
        public string $exportLanguage = 'ar',
        public PersonalDetails $personal = new PersonalDetails,
        public LocalizedText $summary = new LocalizedText,
        public ?array $experience = null,
        public ?array $education = null,
        public ?array $skills = null,
        public ?array $languages = null,
        public ?array $certifications = null,
        public ?array $projects = null,
        public ?array $customSections = null,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        $language = ($value['export_language'] ?? 'ar') === 'en' ? 'en' : 'ar';

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            exportLanguage: $language,
            personal: PersonalDetails::fromArray(is_array($value['personal'] ?? null) ? $value['personal'] : []),
            summary: LocalizedText::fromArray($value['summary'] ?? null),
            experience: self::mapList($value, 'experience', ExperienceEntry::fromArray(...)),
            education: self::mapList($value, 'education', EducationEntry::fromArray(...)),
            skills: self::mapList($value, 'skills', Skill::fromArray(...)),
            languages: self::mapList($value, 'languages', LanguageSkill::fromArray(...)),
            certifications: self::mapList($value, 'certifications', Certification::fromArray(...)),
            projects: self::mapList($value, 'projects', Project::fromArray(...)),
            customSections: self::mapList($value, 'custom_sections', CustomSection::fromArray(...)),
        );
    }

    /**
     * Hydrate from the current GeneratedCv scalar columns / form payload.
     *
     * @param  array<string, mixed>  $cv
     */
    public static function fromLegacy(array $cv): self
    {
        $language = ($cv['language'] ?? 'ar') === 'en' ? 'en' : 'ar';
        $text = static fn (?string $value): LocalizedText => LocalizedText::forLanguage($language, $value);

        $experienceBlob = trim((string) ($cv['experience_input'] ?? ''));
        $educationBlob = trim((string) ($cv['education_input'] ?? ''));
        $certificationsBlob = trim((string) ($cv['certifications_input'] ?? ''));

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            exportLanguage: $language,
            personal: new PersonalDetails(
                fullName: $text((string) ($cv['full_name'] ?? '')),
                headline: $text((string) ($cv['target_job_title'] ?? '')),
                email: self::nullableString($cv['email'] ?? null),
                phone: self::nullableString($cv['phone'] ?? null),
                linkedin: self::nullableString($cv['linkedin'] ?? null),
                location: $text((string) ($cv['location'] ?? '')),
            ),
            summary: $text((string) ($cv['summary_input'] ?? '')),
            experience: LegacySectionParser::experience($experienceBlob, $language),
            education: LegacySectionParser::education($educationBlob, $language),
            skills: self::skillsFromBlob((string) ($cv['skills_input'] ?? ''), $language),
            languages: null,
            certifications: $certificationsBlob === ''
                ? null
                : LegacySectionParser::certifications($certificationsBlob, $language),
            projects: null,
            customSections: null,
        );
    }

    /**
     * Overlay AI-generated markdown onto a legacy/form document.
     *
     * Builder-authored entries that already have a title or employer are kept;
     * generator blobs are replaced with parsed entries so the existing
     * CV layout receives real jobs, not one paragraph. When $formPayload is
     * given, entries that are merely the parse of its free-text inputs count
     * as blobs too, so the AI's structured rewrite wins over them.
     *
     * @param  array<string, mixed>|null  $formPayload
     */
    public function overlayGeneratedMarkdown(string $markdown, string $language, ?string $headline = null, ?array $formPayload = null): self
    {
        $language = $language === 'en' ? 'en' : 'ar';
        $sections = LegacySectionParser::markdownSections($markdown);
        $formDocument = is_array($formPayload) && $formPayload !== [] ? self::fromLegacy($formPayload) : null;
        $fromForm = fn (string $field): bool => $formDocument !== null
            && self::sameEntries($this->{$field}, $formDocument->{$field});

        $personal = $this->personal;
        if (filled($headline)) {
            $personal = new PersonalDetails(
                fullName: $personal->fullName,
                headline: LocalizedText::forLanguage($language, $headline),
                email: $personal->email,
                phone: $personal->phone,
                linkedin: $personal->linkedin,
                location: $personal->location,
            );
        }

        return new self(
            schemaVersion: $this->schemaVersion,
            exportLanguage: $language,
            personal: $personal,
            summary: isset($sections['summary'])
                ? LocalizedText::forLanguage($language, LegacySectionParser::stripEditorial($sections['summary']))
                : $this->summary,
            experience: isset($sections['experience']) && (! $this->hasStructuredExperience() || $fromForm('experience'))
                ? LegacySectionParser::experience($sections['experience'], $language)
                : $this->experience,
            education: isset($sections['education']) && (! $this->hasStructuredEducation() || $fromForm('education'))
                ? LegacySectionParser::education($sections['education'], $language)
                : $this->education,
            skills: isset($sections['skills'])
                ? self::skillsFromBlob($sections['skills'], $language)
                : $this->skills,
            languages: isset($sections['languages']) && empty($this->languages)
                ? LegacySectionParser::languages($sections['languages'], $language)
                : $this->languages,
            certifications: isset($sections['certifications']) && (! $this->hasStructuredCertifications() || $fromForm('certifications'))
                ? LegacySectionParser::certifications($sections['certifications'], $language)
                : $this->certifications,
            projects: isset($sections['projects']) && empty($this->projects)
                ? LegacySectionParser::projects($sections['projects'], $language)
                : $this->projects,
            customSections: $this->overlayCustomSections($sections['custom'] ?? [], $language),
        );
    }

    /**
     * @param  list<JsonSerializable>|null  $a
     * @param  list<JsonSerializable>|null  $b
     */
    private static function sameEntries(?array $a, ?array $b): bool
    {
        $encode = static fn (?array $items): string => json_encode(
            array_map(static fn ($item): mixed => $item->toArray(), $items ?? []),
            JSON_UNESCAPED_UNICODE,
        );

        return $encode($a) === $encode($b);
    }

    /**
     * Keep builder-authored custom sections and add generated ones whose title is new.
     *
     * @param  list<array{title: string, body: string}>  $generated
     * @return list<CustomSection>|null
     */
    private function overlayCustomSections(array $generated, string $language): ?array
    {
        if ($generated === []) {
            return $this->customSections;
        }

        $sections = $this->customSections ?? [];
        $existingTitles = array_map(
            fn (CustomSection $section): string => mb_strtolower(trim($section->title->resolve($language))),
            $sections,
        );

        foreach ($generated as $section) {
            if (in_array(mb_strtolower($section['title']), $existingTitles, true)) {
                continue;
            }
            $sections[] = new CustomSection(
                key: 'custom',
                title: LocalizedText::forLanguage($language, $section['title']),
                body: LocalizedText::forLanguage($language, $section['body']),
            );
        }

        return $sections;
    }

    public function hasStructuredExperience(): bool
    {
        foreach ($this->experience ?? [] as $entry) {
            $hasRole = $entry->title->isNotEmpty();
            $hasEmployer = $entry->company->isNotEmpty();
            $hasDates = $entry->startDate !== null || $entry->endDate !== null || $entry->isCurrent;
            $hasBullets = $entry->bullets !== [];
            if ($hasBullets || ($hasRole && ($hasEmployer || $hasDates))) {
                return true;
            }
        }

        return false;
    }

    public function hasStructuredEducation(): bool
    {
        foreach ($this->education ?? [] as $entry) {
            if ($entry->degree->isNotEmpty() || $entry->institution->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function hasStructuredCertifications(): bool
    {
        foreach ($this->certifications ?? [] as $entry) {
            if ($entry->name->isNotEmpty() || $entry->issuer->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    public function withExportLanguage(string $language): self
    {
        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            exportLanguage: $language === 'en' ? 'en' : 'ar',
            personal: $this->personal,
            summary: $this->summary,
            experience: $this->experience,
            education: $this->education,
            skills: $this->skills,
            languages: $this->languages,
            certifications: $this->certifications,
            projects: $this->projects,
            customSections: $this->customSections,
        );
    }

    public function duplicate(): self
    {
        return $this->withExportLanguage($this->exportLanguage);
    }

    public function resolve(?string $language = null): ResolvedCvDocument
    {
        $language = ($language ?? $this->exportLanguage) === 'en' ? 'en' : 'ar';
        $skills = $this->joinResolved(
            $this->skills,
            fn (Skill $skill) => $skill->name->resolve($language),
        );
        $experience = $this->joinResolved(
            $this->experience,
            fn (ExperienceEntry $entry) => $entry->resolveNarrative($language),
            "\n\n",
        );
        $education = $this->joinResolved(
            $this->education,
            fn (EducationEntry $entry) => $entry->resolveNarrative($language),
            "\n\n",
        );
        $certifications = $this->joinResolved(
            $this->certifications,
            fn (Certification $entry) => $entry->resolveNarrative($language),
            "\n",
        );
        $languages = $this->joinResolved(
            $this->languages,
            function (LanguageSkill $entry) use ($language): string {
                $name = $entry->name->resolve($language);
                $level = $entry->level->resolve($language);

                return trim($name.($level !== '' ? " ({$level})" : ''));
            },
        );
        $projects = $this->joinResolved(
            $this->projects,
            fn (Project $entry) => $entry->resolveNarrative($language),
            "\n\n",
        );
        $summary = $this->summary->resolve($language);
        $fullName = $this->personal->fullName->resolve($language);
        $headline = $this->personal->headline->resolve($language);
        $location = $this->personal->location->resolve($language);

        $blocks = array_values(array_filter([
            $fullName,
            $headline,
            implode(' | ', array_filter([
                $this->personal->email,
                $this->personal->phone,
                $this->personal->linkedin,
                $location,
            ])),
            $summary,
            $skills,
            $experience,
            $education,
            $certifications,
            $languages,
            $projects,
        ], fn (?string $block) => trim((string) $block) !== ''));

        $isAr = $language === 'ar';
        $sectionOrder = $this->populatedSectionKeys();
        $structuredSections = [];

        foreach ($sectionOrder as $key) {
            if ($key === 'summary' && trim($summary) !== '') {
                $structuredSections[] = [
                    'id' => 'summary',
                    'key' => 'summary',
                    'type' => 'text',
                    'title' => $isAr ? 'الملخص المهني' : 'Professional Summary',
                    'content' => $summary,
                ];
            } elseif ($key === 'experience' && ! empty($this->experience)) {
                $entries = [];
                foreach ($this->experience as $entry) {
                    $jobTitle = $entry->title->resolve($language);
                    $company = $entry->company->resolve($language);
                    $loc = $entry->location->resolve($language);
                    $start = self::displayDate($entry->startDate, $language);
                    $end = $entry->isCurrent ? ($isAr ? 'حتى الآن' : 'Present') : self::displayDate($entry->endDate, $language);
                    $dateRange = trim(($start ?? '').($end ? ' – '.$end : ''));
                    $bullets = array_values(array_filter(array_map(
                        fn (LocalizedText $b) => $b->resolve($language),
                        $entry->bullets
                    ), fn ($b) => trim((string) $b) !== ''));
                    $desc = $entry->narrative->resolve($language);

                    if ($jobTitle !== '' || $company !== '' || $desc !== '' || $bullets !== []) {
                        $entries[] = [
                            'title' => $jobTitle,
                            'subtitle' => $company,
                            'location' => $loc,
                            'date_range' => $dateRange,
                            'bullets' => $bullets,
                            'description' => $desc,
                        ];
                    }
                }
                if (! empty($entries)) {
                    $structuredSections[] = [
                        'id' => 'experience',
                        'key' => 'experience',
                        'type' => 'entries',
                        'title' => $isAr ? 'الخبرة المهنية' : 'Experience',
                        'entries' => $entries,
                    ];
                }
            } elseif ($key === 'education' && ! empty($this->education)) {
                $entries = [];
                foreach ($this->education as $entry) {
                    $deg = $entry->degree->resolve($language);
                    $field = $entry->field->resolve($language);
                    $inst = $entry->institution->resolve($language);
                    $title = trim($deg.($field !== '' ? ' - '.$field : ''));
                    if ($title === '') {
                        $title = $deg;
                    }
                    $start = self::displayDate($entry->startDate, $language);
                    $end = self::displayDate($entry->endDate, $language);
                    $dateRange = trim(($start ?? '').($end ? ' – '.$end : ''));
                    $desc = $entry->narrative->resolve($language);

                    if ($title !== '' || $inst !== '' || $desc !== '') {
                        $entries[] = [
                            'title' => $title,
                            'subtitle' => $inst,
                            'date_range' => $dateRange,
                            'description' => $desc,
                        ];
                    }
                }
                if (! empty($entries)) {
                    $structuredSections[] = [
                        'id' => 'education',
                        'key' => 'education',
                        'type' => 'entries',
                        'title' => $isAr ? 'التعليم' : 'Education',
                        'entries' => $entries,
                    ];
                }
            } elseif ($key === 'skills' && trim($skills) !== '') {
                $skillItems = [];
                $groups = [];
                foreach ($this->skills ?? [] as $skill) {
                    $name = trim($skill->name->resolve($language));
                    if ($name !== '') {
                        $skillItems[] = $name;
                        $category = trim($skill->category->resolve($language));
                        $groups[$category][] = $name;
                    }
                }
                $structuredSections[] = [
                    'id' => 'skills',
                    'key' => 'skills',
                    'type' => 'tags',
                    'title' => $isAr ? 'المهارات' : 'Skills',
                    'items' => $skillItems,
                    // Only grouped when at least one skill carries a category.
                    'groups' => count($groups) > 1 || ! isset($groups[''])
                        ? array_map(
                            fn (string $category, array $items): array => ['category' => $category, 'items' => $items],
                            array_map('strval', array_keys($groups)),
                            array_values($groups),
                        )
                        : [],
                    'text' => $skills,
                ];
            } elseif ($key === 'certifications' && ! empty($this->certifications)) {
                $entries = [];
                foreach ($this->certifications as $entry) {
                    $name = $entry->name->resolve($language);
                    $issuer = $entry->issuer->resolve($language);
                    $date = self::displayDate($entry->date, $language);
                    $desc = $entry->narrative->resolve($language);

                    if ($name !== '' || $issuer !== '' || $desc !== '') {
                        $entries[] = [
                            'title' => $name,
                            'subtitle' => $issuer,
                            'date_range' => $date,
                            'description' => $desc,
                        ];
                    }
                }
                if (! empty($entries)) {
                    $structuredSections[] = [
                        'id' => 'certifications',
                        'key' => 'certifications',
                        'type' => 'entries',
                        'title' => $isAr ? 'الشهادات' : 'Certifications',
                        'entries' => $entries,
                    ];
                }
            } elseif ($key === 'languages' && ! empty($this->languages)) {
                $entries = [];
                foreach ($this->languages as $entry) {
                    $name = $entry->name->resolve($language);
                    $level = $entry->level->resolve($language);
                    if ($name !== '') {
                        $entries[] = [
                            'title' => $name,
                            'subtitle' => $level,
                        ];
                    }
                }
                if (! empty($entries)) {
                    $structuredSections[] = [
                        'id' => 'languages',
                        'key' => 'languages',
                        'type' => 'entries',
                        'title' => $isAr ? 'اللغات' : 'Languages',
                        'entries' => $entries,
                    ];
                }
            } elseif ($key === 'projects' && ! empty($this->projects)) {
                $entries = [];
                foreach ($this->projects as $entry) {
                    $name = $entry->name->resolve($language);
                    $desc = $entry->description->resolve($language);
                    $bullets = array_values(array_filter(array_map(
                        fn (LocalizedText $b) => $b->resolve($language),
                        $entry->bullets
                    ), fn ($b) => trim((string) $b) !== ''));

                    if ($name !== '' || $desc !== '' || $bullets !== []) {
                        $entries[] = [
                            'title' => $name,
                            'subtitle' => $entry->url,
                            'bullets' => $bullets,
                            'description' => $desc,
                        ];
                    }
                }
                if (! empty($entries)) {
                    $structuredSections[] = [
                        'id' => 'projects',
                        'key' => 'projects',
                        'type' => 'entries',
                        'title' => $isAr ? 'المشاريع' : 'Projects',
                        'entries' => $entries,
                    ];
                }
            } elseif ($key === 'custom_sections' && ! empty($this->customSections)) {
                foreach ($this->customSections as $idx => $entry) {
                    $title = $entry->title->resolve($language);
                    $body = $entry->body->resolve($language);
                    if ($title !== '' || $body !== '') {
                        $structuredSections[] = [
                            'id' => 'custom_'.$entry->key.'_'.$idx,
                            'key' => $entry->key,
                            'type' => 'text',
                            'title' => $title !== '' ? $title : ($isAr ? 'قسم إضافي' : 'Additional Section'),
                            'content' => $body,
                        ];
                    }
                }
            }
        }

        return new ResolvedCvDocument(
            language: $language,
            fullName: $fullName,
            headline: $headline,
            email: $this->personal->email,
            phone: $this->personal->phone,
            linkedin: $this->personal->linkedin,
            location: $location,
            summary: $summary,
            skills: $skills,
            experience: $experience,
            education: $education,
            certifications: $certifications,
            languages: $languages,
            projects: $projects,
            plainText: implode("\n\n", $blocks),
            sectionOrder: $sectionOrder,
            structuredSections: $structuredSections,
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(): array
    {
        $paths = $this->personal->missingTranslations();
        $summaryGap = $this->summary->missingCounterpart();
        if ($summaryGap !== null) {
            $paths[] = 'summary.'.$summaryGap;
        }

        foreach ($this->experience ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('experience.'.$index));
        }
        foreach ($this->education ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('education.'.$index));
        }
        foreach ($this->skills ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('skills.'.$index));
        }
        foreach ($this->languages ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('languages.'.$index));
        }
        foreach ($this->certifications ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('certifications.'.$index));
        }
        foreach ($this->projects ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('projects.'.$index));
        }
        foreach ($this->customSections ?? [] as $index => $entry) {
            array_push($paths, ...$entry->missingTranslations('custom_sections.'.$index));
        }

        return array_values($paths);
    }

    /**
     * Canonical section keys that currently hold user content.
     *
     * @return list<string>
     */
    public function populatedSectionKeys(): array
    {
        $keys = [];
        if (! $this->summary->isEmpty()) {
            $keys[] = 'summary';
        }
        if ($this->listHasContent($this->experience, fn (ExperienceEntry $entry) => ! $entry->narrative->isEmpty()
            || $entry->bullets !== []
            || ! $entry->company->isEmpty()
            || ! $entry->title->isEmpty())) {
            $keys[] = 'experience';
        }
        if ($this->listHasContent($this->education, fn (EducationEntry $entry) => ! $entry->narrative->isEmpty()
            || ! $entry->institution->isEmpty()
            || ! $entry->degree->isEmpty())) {
            $keys[] = 'education';
        }
        if ($this->listHasContent($this->skills, fn (Skill $entry) => ! $entry->name->isEmpty())) {
            $keys[] = 'skills';
        }
        if ($this->listHasContent($this->languages, fn (LanguageSkill $entry) => ! $entry->name->isEmpty())) {
            $keys[] = 'languages';
        }
        if ($this->listHasContent($this->certifications, fn (Certification $entry) => ! $entry->name->isEmpty()
            || ! $entry->narrative->isEmpty())) {
            $keys[] = 'certifications';
        }
        if ($this->listHasContent($this->projects, fn (Project $entry) => ! $entry->name->isEmpty()
            || ! $entry->description->isEmpty()
            || $entry->bullets !== [])) {
            $keys[] = 'projects';
        }
        if ($this->listHasContent($this->customSections, fn (CustomSection $entry) => ! $entry->title->isEmpty()
            || ! $entry->body->isEmpty())) {
            $keys[] = 'custom_sections';
        }

        return $keys;
    }

    /**
     * @template T
     *
     * @param  list<T>|null  $items
     * @param  callable(T): bool  $hasContent
     */
    private function listHasContent(?array $items, callable $hasContent): bool
    {
        if ($items === null || $items === []) {
            return false;
        }

        foreach ($items as $item) {
            if ($hasContent($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snapshot of resolved values for the current export language (legacy columns).
     *
     * @return array<string, string|null>
     */
    public function legacySnapshot(): array
    {
        $resolved = $this->resolve();

        return [
            'full_name' => $resolved->fullName,
            'email' => $resolved->email,
            'phone' => $resolved->phone,
            'linkedin' => $resolved->linkedin,
            'location' => $resolved->location !== '' ? $resolved->location : null,
            'target_job_title' => $resolved->headline,
            'language' => $this->exportLanguage,
            'summary_input' => $resolved->summary !== '' ? $resolved->summary : null,
            'skills_input' => $resolved->skills,
            'experience_input' => $resolved->experience,
            'education_input' => $resolved->education,
            'certifications_input' => $resolved->certifications !== '' ? $resolved->certifications : null,
        ];
    }

    /**
     * JSON Schema for ATS / template consumers.
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        $localized = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ar', 'en'],
            'properties' => [
                'ar' => ['type' => 'string'],
                'en' => ['type' => 'string'],
            ],
        ];

        return [
            '$id' => 'sirati.cv_document.v'.self::SCHEMA_VERSION,
            'title' => 'Siratie CV Document',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['schema_version', 'export_language', 'personal', 'summary'],
            'properties' => [
                'schema_version' => ['type' => 'integer', 'const' => self::SCHEMA_VERSION],
                'export_language' => ['type' => 'string', 'enum' => ['ar', 'en']],
                'personal' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['full_name', 'headline', 'email', 'phone', 'linkedin', 'location'],
                    'properties' => [
                        'full_name' => $localized,
                        'headline' => $localized,
                        'email' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']],
                        'linkedin' => ['type' => ['string', 'null']],
                        'location' => $localized,
                    ],
                ],
                'summary' => $localized,
                'experience' => ['type' => 'array', 'items' => ['type' => 'object']],
                'education' => ['type' => 'array', 'items' => ['type' => 'object']],
                'skills' => ['type' => 'array', 'items' => ['type' => 'object']],
                'languages' => ['type' => 'array', 'items' => ['type' => 'object']],
                'certifications' => ['type' => 'array', 'items' => ['type' => 'object']],
                'projects' => ['type' => 'array', 'items' => ['type' => 'object']],
                'custom_sections' => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'schema_version' => self::SCHEMA_VERSION,
            'export_language' => $this->exportLanguage,
            'personal' => $this->personal->toArray(),
            'summary' => $this->summary->toArray(),
        ];

        foreach ([
            'experience' => $this->experience,
            'education' => $this->education,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'certifications' => $this->certifications,
            'projects' => $this->projects,
            'custom_sections' => $this->customSections,
        ] as $key => $section) {
            if ($section === null) {
                continue;
            }

            $data[$key] = array_map(
                fn (JsonSerializable $item) => $item->toArray(),
                $section,
            );
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $value
     * @param  callable(mixed): T  $mapper
     * @return list<T>|null
     */
    private static function mapList(array $value, string $key, callable $mapper): ?array
    {
        if (! array_key_exists($key, $value)) {
            return null;
        }

        if (! is_array($value[$key])) {
            return [];
        }

        $items = [];
        foreach ($value[$key] as $item) {
            $items[] = $mapper($item);
        }

        return $items;
    }

    /**
     * @template T
     *
     * @param  list<T>|null  $items
     * @param  callable(T): string  $resolver
     */
    private function joinResolved(?array $items, callable $resolver, string $separator = ', '): string
    {
        if ($items === null) {
            return '';
        }

        $parts = [];
        foreach ($items as $item) {
            $resolved = trim($resolver($item));
            if ($resolved !== '') {
                $parts[] = $resolved;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @return list<Skill>
     */
    private static function skillsFromBlob(string $blob, string $language): array
    {
        $skills = [];

        foreach (preg_split('/\R/u', $blob) ?: [] as $line) {
            // "Category | a, b" or "Category: a, b" (optionally bulleted/bold) keeps its group.
            $line = trim((string) preg_replace('/^[-*•]\s+/u', '', trim($line)));
            $category = '';
            // A single "Category | item" pipe also counts, so one-skill groups keep their label.
            if (preg_match('/^\**([^|:,;()،]{2,60}?)\**\s*([|:])\s*\**\s*(.+)$/u', $line, $match) === 1
                && (preg_match('/[,;،]/u', $match[3]) === 1 || ($match[2] === '|' && ! str_contains($match[3], '|')))) {
                $category = trim($match[1]);
                $line = $match[3];
            }

            // Uncategorised lines may also use "a | b | c" or the Arabic comma.
            $separators = $category === '' ? '/[,;،]+(?![^()]*\))|\s+\|\s+/u' : '/[,;]+(?![^()]*\))/u';
            foreach (preg_split($separators, $line) ?: [] as $part) {
                $part = trim($part, " \t*");
                if ($part !== '') {
                    $skills[] = new Skill(
                        name: LocalizedText::forLanguage($language, $part),
                        category: LocalizedText::forLanguage($language, $category),
                    );
                }
            }
        }

        return $skills;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private const MONTHS = [
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'ar' => ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    ];

    /**
     * Reader-facing form of a stored date. ISO "YYYY-MM" / "YYYY-MM-DD" and
     * "MM/YYYY" (what the app's pickers store) print as "Nov 2025"; a day is
     * never shown on a CV. Anything else is the author's own text and
     * stays as written.
     */
    public static function displayDate(?string $date, string $language): ?string
    {
        if ($date === null) {
            return null;
        }
        if (preg_match('/^\s*(\d{4})-(\d{1,2})(?:-\d{1,2})?(?:[T ][\d:.+\-Z]*)?\s*$/', $date, $match) === 1) {
            [$year, $month] = [$match[1], (int) $match[2]];
        } elseif (preg_match('/^\s*(\d{1,2})\/(\d{4})\s*$/', $date, $match) === 1) {
            // "MM/YYYY": what the app's month picker writes.
            [$year, $month] = [$match[2], (int) $match[1]];
        } else {
            return $date;
        }
        if ($month < 1 || $month > 12) {
            return $date;
        }

        return self::MONTHS[$language === 'en' ? 'en' : 'ar'][$month - 1].' '.$year;
    }
}
