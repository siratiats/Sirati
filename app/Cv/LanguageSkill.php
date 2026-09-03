<?php

namespace App\Cv;

use JsonSerializable;

final readonly class LanguageSkill implements JsonSerializable
{
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $level = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            level: LocalizedText::fromArray($value['level'] ?? null),
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
     * @return array{name: array{ar: string, en: string}, level: array{ar: string, en: string}}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name->toArray(),
            'level' => $this->level->toArray(),
        ];
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
