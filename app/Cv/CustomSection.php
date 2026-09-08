<?php

namespace App\Cv;

use JsonSerializable;

final readonly class CustomSection implements JsonSerializable
{
    /**
     * @param  list<LocalizedText>  $items
     */
    public function __construct(
        public string $key,
        public LocalizedText $title = new LocalizedText,
        public LocalizedText $body = new LocalizedText,
        public array $items = [],
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        $key = trim((string) ($value['key'] ?? ''));
        $rawItems = $value['items'] ?? [];
        $items = [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                $text = LocalizedText::fromArray($item);
                if (! $text->isEmpty()) {
                    $items[] = $text;
                }
            }
        }

        return new self(
            key: $key !== '' ? $key : 'custom',
            title: LocalizedText::fromArray($value['title'] ?? null),
            body: LocalizedText::fromArray($value['narrative'] ?? $value['body'] ?? null),
            items: $items,
            id: isset($value['id']) ? (string) $value['id'] : null,
        );
    }

    /**
     * @return list<string>
     */
    public function missingTranslations(string $prefix): array
    {
        return array_values(array_filter([
            self::gap($prefix.'.title', $this->title),
            self::gap($prefix.'.body', $this->body),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'title' => $this->title->toArray(),
            'body' => $this->body->toArray(),
            'narrative' => $this->body->toArray(),
        ];

        if (! empty($this->items)) {
            $data['items'] = array_map(fn (LocalizedText $item) => $item->toArray(), $this->items);
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
