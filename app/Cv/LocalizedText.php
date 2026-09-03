<?php

namespace App\Cv;

use JsonSerializable;

final readonly class LocalizedText implements JsonSerializable
{
    public function __construct(
        public string $ar = '',
        public string $en = '',
    ) {}

    public static function make(string $ar = '', string $en = ''): self
    {
        return new self(ar: trim($ar), en: trim($en));
    }

    public static function forLanguage(string $language, ?string $text): self
    {
        $text = trim((string) $text);

        return $language === 'en' ? new self(en: $text) : new self(ar: $text);
    }

    public static function fromArray(mixed $value): self
    {
        if (is_string($value)) {
            return new self(ar: trim($value));
        }

        if (! is_array($value)) {
            return new self;
        }

        return new self(
            ar: trim((string) ($value['ar'] ?? '')),
            en: trim((string) ($value['en'] ?? '')),
        );
    }

    public function resolve(string $language, bool $fallback = true): string
    {
        $primary = $language === 'en' ? $this->en : $this->ar;

        if ($primary !== '' || ! $fallback) {
            return $primary;
        }

        return $language === 'en' ? $this->ar : $this->en;
    }

    public function isEmpty(): bool
    {
        return $this->ar === '' && $this->en === '';
    }

    /**
     * Translation gap when one language is filled and the other is not.
     */
    public function missingCounterpart(): ?string
    {
        if ($this->ar !== '' && $this->en === '') {
            return 'en';
        }

        if ($this->en !== '' && $this->ar === '') {
            return 'ar';
        }

        return null;
    }

    public function withAr(string $ar): self
    {
        return new self(ar: trim($ar), en: $this->en);
    }

    public function withEn(string $en): self
    {
        return new self(ar: $this->ar, en: trim($en));
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function toArray(): array
    {
        return [
            'ar' => $this->ar,
            'en' => $this->en,
        ];
    }

    /**
     * @return array{ar: string, en: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
