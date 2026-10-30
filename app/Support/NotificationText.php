<?php

namespace App\Support;

use App\Services\I18n\ServerText;

/**
 * A notification's title and body as catalog labels plus their values
 * (Abobker85/Sirati#12). Stored with the in-app notification, so the list
 * shows it in the reader's app language and an admin edit applies to it;
 * the push itself is rendered once, in the user's notification language.
 */
final readonly class NotificationText
{
    /**
     * @param  array<string, scalar>  $params
     */
    public function __construct(
        public string $titleKey,
        public string $bodyKey,
        public array $params = [],
    ) {}

    public function title(?string $language = null): string
    {
        return ServerText::t($this->titleKey, $this->params, $language);
    }

    public function body(?string $language = null): string
    {
        return ServerText::t($this->bodyKey, $this->params, $language);
    }

    /**
     * Columns of a mobile_notifications row: the text as sent, and the keys
     * that render it again in another language.
     *
     * @return array{title: string, body: string, title_key: string, body_key: string, text_params: array<string, scalar>}
     */
    public function attributes(?string $language = null): array
    {
        return [
            'title' => $this->title($language),
            'body' => $this->body($language),
            'title_key' => $this->titleKey,
            'body_key' => $this->bodyKey,
            'text_params' => $this->params,
        ];
    }

    /** @return array{title_key: string, body_key: string, params: array<string, scalar>} */
    public function toArray(): array
    {
        return ['title_key' => $this->titleKey, 'body_key' => $this->bodyKey, 'params' => $this->params];
    }

    public static function fromArray(?array $data): ?self
    {
        if (! is_string($data['title_key'] ?? null) || ! is_string($data['body_key'] ?? null)) {
            return null;
        }

        return new self($data['title_key'], $data['body_key'], is_array($data['params'] ?? null) ? $data['params'] : []);
    }
}
