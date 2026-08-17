<?php

namespace App\Support;

final readonly class DailyNotificationCandidate
{
    public function __construct(
        public string $ruleKey,
        public string $templateKey,
        public string $type,
        public string $actionType,
        public string $actionUrl,
        public array $context = [],
    ) {}
}
