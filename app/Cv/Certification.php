<?php

namespace App\Cv;

use JsonSerializable;

final readonly class Certification implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $issuer = new LocalizedText,
        public ?string $date = null,
        public ?string $expiryDate = null,
        public LocalizedText $narrative = new LocalizedText,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            issuer: LocalizedText::fromArray($value['authority'] ?? $value['issuer'] ?? null),
            date: self::nullableString($value['issue_date'] ?? $value['date'] ?? null),
            expiryDate: self::nullableString($value['expiry_date'] ?? null),
            narrative: LocalizedText::fromArray($value['narrative'] ?? null),
            id: isset($value['id']) ? (string) $value['id'] : null,
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
        $data = [
            'name' => $this->name->toArray(),
            'authority' => $this->issuer->toArray(),
            'issuer' => $this->issuer->toArray(),
            'issue_date' => $this->date,
            'date' => $this->date,
            'narrative' => $this->narrative->toArray(),
        ];

        if ($this->expiryDate !== null) {
            $data['expiry_date'] = $this->expiryDate;
        }

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
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
