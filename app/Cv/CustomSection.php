<?php

namespace App\Cv;

use JsonSerializable;

final readonly class CustomSection implements JsonSerializable
{
    public function __construct(
        public string $key,
        public LocalizedText $title = new LocalizedText,
        public LocalizedText $body = new LocalizedText,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromArray(array $value): self
    {
        $key = trim((string) ($value['key'] ?? ''));

        return new self(
            key: $key !== '' ? $key : 'custom',
            title: LocalizedText::fromArray($value['title'] ?? null),
            body: LocalizedText::fromArray($value['body'] ?? null),
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
     * @return array{key: string, title: array{ar: string, en: string}, body: array{ar: string, en: string}}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title->toArray(),
            'body' => $this->body->toArray(),
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
