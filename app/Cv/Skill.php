<?php

namespace App\Cv;

use JsonSerializable;

final readonly class Skill implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>|string  $value
     */
    public static function fromArray(array|string $value): self
    {
        if (is_string($value)) {
            return new self(name: LocalizedText::fromArray($value));
        }

        return new self(name: LocalizedText::fromArray($value['name'] ?? $value));
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        $missing = $this->name->missingCounterpart();

        return $missing === null ? [] : [$prefix.'.name.'.$missing];
    }

    /**
     * @return array{name: array{ar: string, en: string}}
     */
    public function toArray(): array
    {
        return ['name' => $this->name->toArray()];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
