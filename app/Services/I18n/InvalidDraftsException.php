<?php

namespace App\Services\I18n;

use RuntimeException;

final class InvalidDraftsException extends RuntimeException
{
    /** @param array<string, list<string>> $errors key => errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(count($errors).' draft value(s) are invalid: '.implode(', ', array_keys($errors)).'.');
    }
}
