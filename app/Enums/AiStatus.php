<?php

namespace App\Enums;

enum AiStatus: string
{
    case NotConfigured = 'not_configured';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
