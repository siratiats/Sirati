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
        public LocalizedText $role = new LocalizedText,
        public ?string $url = null,
        public LocalizedText $description = new LocalizedText,
        public array $bullets = [],
        public ?string $id = null,
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
            name: LocalizedText::fromArray($value['title'] ?? $value['name'] ?? null),
            role: LocalizedText::fromArray($value['role'] ?? null),
            url: $url === '' ? null : $url,
            description: LocalizedText::fromArray($value['narrative'] ?? $value['description'] ?? null),
            bullets: $bullets,
            id: isset($value['id']) ? (string) $value['id'] : null,
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        $paths = array_values(array_filter([
            self::gap($prefix.'.name', $this->name),
            self::gap($prefix.'.role', $this->role),
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
            $this->role->resolve($language),
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
        $data = [
            'title' => $this->name->toArray(),
            'name' => $this->name->toArray(),
            'role' => $this->role->toArray(),
            'url' => $this->url,
            'narrative' => $this->description->toArray(),
            'description' => $this->description->toArray(),
        ];

        if (! empty($this->bullets)) {
            $data['bullets'] = array_map(fn (LocalizedText $bullet) => $bullet->toArray(), $this->bullets);
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
