<?php

namespace App\Cv;

final readonly class ResolvedCvDocument
{
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
    ) {}
}
