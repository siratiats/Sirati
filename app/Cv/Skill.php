<?php

namespace App\Cv;

use JsonSerializable;

final readonly class Skill implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $level = new LocalizedText,
        public LocalizedText $category = new LocalizedText,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>|string  $value
     */
    public static function fromArray(array|string $value): self
    {
        if (is_string($value)) {
            return new self(name: LocalizedText::fromArray($value));
        }

        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            level: ProficiencyStorageKeys::fillEmptyEnglish(
                LocalizedText::fromArray($value['level'] ?? null),
                ProficiencyStorageKeys::SKILL,
            ),
            category: ProficiencyStorageKeys::fillEmptyEnglish(
                LocalizedText::fromArray($value['category'] ?? null),
                ProficiencyStorageKeys::SKILL,
            ),
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
            self::gap($prefix.'.level', $this->level),
            self::gap($prefix.'.category', $this->category),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name->toArray(),
            'level' => $this->level->toArray(),
        ];

        if ($this->category->isNotEmpty()) {
            $data['category'] = $this->category->toArray();
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

    private static function gap(string $path, LocalizedText $text): ?string
    {
        $missing = $text->missingCounterpart();

        return $missing === null ? null : $path.'.'.$missing;
    }
}
