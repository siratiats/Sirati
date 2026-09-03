<?php

namespace App\Cv;

use JsonSerializable;

final readonly class PersonalDetails implements JsonSerializable
{
    public function __construct(
        public LocalizedText $fullName = new LocalizedText,
        public LocalizedText $headline = new LocalizedText,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $linkedin = null,
        public LocalizedText $location = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            fullName: LocalizedText::fromArray($value['full_name'] ?? null),
            headline: LocalizedText::fromArray($value['headline'] ?? null),
            email: self::nullableString($value['email'] ?? null),
            phone: self::nullableString($value['phone'] ?? null),
            linkedin: self::nullableString($value['linkedin'] ?? null),
            location: LocalizedText::fromArray($value['location'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix = 'personal'): array
    {
        return array_values(array_filter([
            self::gap($prefix.'.full_name', $this->fullName),
            self::gap($prefix.'.headline', $this->headline),
            self::gap($prefix.'.location', $this->location),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'full_name' => $this->fullName->toArray(),
            'headline' => $this->headline->toArray(),
            'email' => $this->email,
            'phone' => $this->phone,
            'linkedin' => $this->linkedin,
            'location' => $this->location->toArray(),
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
