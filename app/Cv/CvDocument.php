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
            experience: $experienceBlob === ''
                ? []
                : [new ExperienceEntry(narrative: $text($experienceBlob))],
            education: $educationBlob === ''
                ? []
                : [new EducationEntry(narrative: $text($educationBlob))],
            skills: self::skillsFromBlob((string) ($cv['skills_input'] ?? ''), $language),
            languages: null,
            certifications: $certificationsBlob === ''
                ? null
                : [new Certification(narrative: $text($certificationsBlob))],
            projects: null,
            customSections: null,
        );
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
            $paths = [...$paths, ...$entry->missingTranslations('experience.'.$index)];
        }
        foreach ($this->education ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('education.'.$index)];
        }
        foreach ($this->skills ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('skills.'.$index)];
        }
        foreach ($this->languages ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('languages.'.$index)];
        }
        foreach ($this->certifications ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('certifications.'.$index)];
        }
        foreach ($this->projects ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('projects.'.$index)];
        }
        foreach ($this->customSections ?? [] as $index => $entry) {
            $paths = [...$paths, ...$entry->missingTranslations('custom_sections.'.$index)];
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
            'title' => 'Sirati CV Document',
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
        $parts = preg_split('/[,;\n]+/u', $blob) ?: [];
        $skills = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $skills[] = new Skill(name: LocalizedText::forLanguage($language, $part));
            }
        }

        return $skills;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
