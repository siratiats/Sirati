<?php

namespace App\Services\Admin;

use App\Support\FailClosed;

/**
 * The single place that decides who may use the admin panel.
 *
 * ADMIN_EMAILS lists the accounts allowed in, compared case-insensitively.
 * Outside local/testing an empty list fails closed with a 503.
 */
class AdminAllowList
{
    public function assertConfigured(): void
    {
        if ($this->emails() === [] && ! FailClosed::allowsMissingConfiguration()) {
            FailClosed::unavailable('Admin panel', 'services.admin.emails');
        }
    }

    public function allows(?string $email): bool
    {
        $this->assertConfigured();
        $allowed = $this->emails();

        // Local development without a list lets any signed-in account in.
        if ($allowed === []) {
            return true;
        }

        return $email !== null && in_array(self::normalize($email), $allowed, true);
    }

    /** @return list<string> */
    private function emails(): array
    {
        $configured = config('services.admin.emails');
        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(self::normalize(...), array_filter($configured, 'is_string')),
            static fn (string $email): bool => $email !== '',
        ));
    }

    private static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
