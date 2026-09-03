<?php

namespace App\Cv;

use JsonSerializable;

final readonly class Project implements JsonSerializable
{
    /**
     * @param  list<LocalizedText>  $bullets
     */
    public function __construct(
        public LocalizedText $name = new LocalizedText,
        public LocalizedText $description = new LocalizedText,
        public ?string $url = null,
        public array $bullets = [],
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        $bullets = [];
        foreach (is_array($value['bullets'] ?? null) ? $value['bullets'] : [] as $item) {
            $text = LocalizedText::fromArray($item);
            if (! $text->isEmpty()) {
                $bullets[] = $text;
            }
        }

        $url = trim((string) ($value['url'] ?? ''));

        return new self(
            name: LocalizedText::fromArray($value['name'] ?? null),
            description: LocalizedText::fromArray($value['description'] ?? null),
            url: $url === '' ? null : $url,
            bullets: $bullets,
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        $paths = array_values(array_filter([
            self::gap($prefix.'.name', $this->name),
            self::gap($prefix.'.description', $this->description),
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
            $this->name->resolve($language),
            $this->description->resolve($language),
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
            'name' => $this->name->toArray(),
            'description' => $this->description->toArray(),
            'url' => $this->url,
            'bullets' => array_map(fn (LocalizedText $bullet) => $bullet->toArray(), $this->bullets),
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
