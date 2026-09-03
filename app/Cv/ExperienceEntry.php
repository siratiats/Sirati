<?php

namespace App\Cv;

use JsonSerializable;

final readonly class ExperienceEntry implements JsonSerializable
{
    /**
     * @param  list<LocalizedText>  $bullets
     */
    public function __construct(
        public LocalizedText $company = new LocalizedText,
        public LocalizedText $title = new LocalizedText,
        public LocalizedText $location = new LocalizedText,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public bool $isCurrent = false,
        public array $bullets = [],
        public LocalizedText $narrative = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            company: LocalizedText::fromArray($value['company'] ?? null),
            title: LocalizedText::fromArray($value['title'] ?? null),
            location: LocalizedText::fromArray($value['location'] ?? null),
            startDate: self::nullableString($value['start_date'] ?? null),
            endDate: self::nullableString($value['end_date'] ?? null),
            isCurrent: (bool) ($value['is_current'] ?? false),
            bullets: self::texts($value['bullets'] ?? []),
            narrative: LocalizedText::fromArray($value['narrative'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        $paths = array_values(array_filter([
            self::gap($prefix.'.company', $this->company),
            self::gap($prefix.'.title', $this->title),
            self::gap($prefix.'.location', $this->location),
            self::gap($prefix.'.narrative', $this->narrative),
        ]));

        foreach ($this->bullets as $index => $bullet) {
            $gap = self::gap($prefix.'.bullets.'.$index, $bullet);
            if ($gap !== null) {
                $paths[] = $gap;
            }
        }

        return $paths;
    }

    public function resolveNarrative(string $language): string
    {
        $parts = array_values(array_filter([
            $this->title->resolve($language),
            $this->company->resolve($language),
            $this->narrative->resolve($language),
            ...array_map(fn (LocalizedText $bullet) => $bullet->resolve($language), $this->bullets),
        ], fn (string $part) => $part !== ''));

        return implode("\n", $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company' => $this->company->toArray(),
            'title' => $this->title->toArray(),
            'location' => $this->location->toArray(),
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_current' => $this->isCurrent,
            'bullets' => array_map(fn (LocalizedText $bullet) => $bullet->toArray(), $this->bullets),
            'narrative' => $this->narrative->toArray(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<LocalizedText>
     */
    private static function texts(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $texts = [];
        foreach ($value as $item) {
            $text = LocalizedText::fromArray($item);
            if (! $text->isEmpty()) {
                $texts[] = $text;
            }
        }

        return $texts;
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
