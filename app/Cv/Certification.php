<?php

namespace App\Cv;

use JsonSerializable;

final readonly class Certification implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $issuer = new LocalizedText,
        public ?string $date = null,
        public LocalizedText $narrative = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            issuer: LocalizedText::fromArray($value['issuer'] ?? null),
            date: self::nullableString($value['date'] ?? null),
            narrative: LocalizedText::fromArray($value['narrative'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        return array_values(array_filter([
            self::gap($prefix.'.name', $this->name),
            self::gap($prefix.'.issuer', $this->issuer),
            self::gap($prefix.'.narrative', $this->narrative),
        ]));
    }

    public function resolveNarrative(string $language): string
    {
        $parts = array_values(array_filter([
            $this->name->resolve($language),
            $this->issuer->resolve($language),
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
            'name' => $this->name->toArray(),
            'issuer' => $this->issuer->toArray(),
            'date' => $this->date,
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
