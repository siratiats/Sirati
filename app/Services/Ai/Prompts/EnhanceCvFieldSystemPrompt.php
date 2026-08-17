<?php

namespace App\Services\Ai\Prompts;

use InvalidArgumentException;

final class EnhanceCvFieldSystemPrompt
{
    public static function for(string $field): string
    {
        $guidance = match ($field) {
            'experience' => implode("\n", [
                'Convert duties into achievements using strong Arabic action verbs such as قدت، طورت، أدرت، رفعت، خفضت.',
                'Keep each bullet to one line. Quantify only when the draft already contains that number.',
            ]),
            'skills' => implode("\n", [
                'Group skills into technical skills, tools, and soft skills where the draft supports those groups.',
                'Align wording to the target role and remove context-free generic filler such as العمل الجماعي.',
            ]),
            'summary' => 'Produce 3–5 role-focused lines using only facts in this draft. Do not infer facts from an empty or unstated field.',
            'education' => 'Normalize degree, institution, field, and years into a consistent order. Include only values present in the draft.',
            'certifications' => 'Normalize certification name and issuer. Never invent an issuing body.',
            default => throw new InvalidArgumentException("Unsupported CV field: {$field}"),
        };

        return implode("\n\n", [
            'You improve one field of an ATS-friendly CV. Return only the required JSON shape.',
            'Rewrite ONLY what the user wrote. Never add employers, job titles, dates, durations, metrics, percentages, certifications, institutions, or technologies that are not present in the draft. If a strong CV would normally include such a detail and it is absent, do NOT invent a placeholder or plausible value—list the specific fact in missing_facts for the user to supply.',
            $guidance,
            ArabicRegisterRules::section(),
        ]);
    }
}
