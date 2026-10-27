<?php

namespace App\Services\I18n;

use RuntimeException;

final class IcuSyntaxException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $position = null)
    {
        parent::__construct($position === null ? $message : $message.' (at character '.$position.')');
    }
}
