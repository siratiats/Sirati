<?php

namespace App\Cv;

use JsonSerializable;

final readonly class LanguageSkill implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $level = new LocalizedText,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            level: ProficiencyStorageKeys::fillEmptyEnglish(
                LocalizedText::fromArray($value['level'] ?? null),
                ProficiencyStorageKeys::LANGUAGE_LEVEL,
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
