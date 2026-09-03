<?php

namespace App\Cv;

use JsonSerializable;

final readonly class EducationEntry implements JsonSerializable
{
    public function __construct(
        public LocalizedText $institution = new LocalizedText,
        public LocalizedText $degree = new LocalizedText,
        public LocalizedText $field = new LocalizedText,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public LocalizedText $narrative = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            institution: LocalizedText::fromArray($value['institution'] ?? null),
            degree: LocalizedText::fromArray($value['degree'] ?? null),
            field: LocalizedText::fromArray($value['field'] ?? null),
            startDate: self::nullableString($value['start_date'] ?? null),
            endDate: self::nullableString($value['end_date'] ?? null),
            narrative: LocalizedText::fromArray($value['narrative'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        return array_values(array_filter([
            self::gap($prefix.'.institution', $this->institution),
            self::gap($prefix.'.degree', $this->degree),
            self::gap($prefix.'.field', $this->field),
            self::gap($prefix.'.narrative', $this->narrative),
        ]));
    }

    public function resolveNarrative(string $language): string
    {
        $parts = array_values(array_filter([
            $this->degree->resolve($language),
            $this->field->resolve($language),
            $this->institution->resolve($language),
            $this->narrative->resolve($language),
        ], fn (string $part) => $part !== ''));

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'institution' => $this->institution->toArray(),
            'degree' => $this->degree->toArray(),
            'field' => $this->field->toArray(),
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'narrative' => $this->narrative->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function gap(string $path, LocalizedText $text): ?string
    {
        $missing = $text->missingCounterpart();

        return $missing === null ? null : $path.'.'.$missing;
    }
}
