<?php

namespace App\Cv;

final readonly class ResolvedCvDocument
{
    /**
     * @param list<string> $sectionOrder
     * @param list<array<string, mixed>> $structuredSections
     */
    public function __construct(
        public string $language,
        public string $fullName,
        public string $headline,
        public ?string $email,
        public ?string $phone,
        public ?string $linkedin,
        public string $location,
        public string $summary,
        public string $skills,
        public string $experience,
        public string $education,
        public string $certifications,
        public string $languages,
        public string $projects,
        public string $plainText,
        public array $sectionOrder = [],
        public array $structuredSections = [],
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function toStructuredSections(): array
    {
        return $this->structuredSections;
    }

    public function toMarkdown(): string
    {
        $isAr = $this->language === 'ar';
        $sections = [];

        if (trim($this->summary) !== '') {
            $title = $isAr ? 'الملخص المهني' : 'Professional Summary';
            $sections[] = "## {$title}\n\n" . trim($this->summary);
        }

        if (trim($this->experience) !== '') {
            $title = $isAr ? 'الخبرة المهنية' : 'Experience';
            $sections[] = "## {$title}\n\n" . trim($this->experience);
        }

        if (trim($this->education) !== '') {
            $title = $isAr ? 'التعليم' : 'Education';
            $sections[] = "## {$title}\n\n" . trim($this->education);
        }

        if (trim($this->skills) !== '') {
            $title = $isAr ? 'المهارات' : 'Skills';
            $sections[] = "## {$title}\n\n" . trim($this->skills);
        }

        if (trim($this->certifications) !== '') {
            $title = $isAr ? 'الشهادات' : 'Certifications';
            $sections[] = "## {$title}\n\n" . trim($this->certifications);
        }

        if (trim($this->languages) !== '') {
            $title = $isAr ? 'اللغات' : 'Languages';
            $sections[] = "## {$title}\n\n" . trim($this->languages);
        }

        if (trim($this->projects) !== '') {
            $title = $isAr ? 'المشاريع' : 'Projects';
            $sections[] = "## {$title}\n\n" . trim($this->projects);
        }

        return implode("\n\n", $sections);
    }
}
