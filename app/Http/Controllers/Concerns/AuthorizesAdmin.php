<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Admin gate shared by every admin controller.
 *
 * ADMIN_EMAILS lists the accounts allowed in. Outside local/testing an empty
 * list fails closed (AGENTS.md rule 7): nobody gets in until it is set.
 */
trait AuthorizesAdmin
{
    protected function authorizeAdmin(Request $request): void
    {
        $allowedEmails = config('services.admin.emails', []);

        if ($allowedEmails !== [] && ! in_array($request->user()?->email, $allowedEmails, true)) {
            throw new AccessDeniedHttpException('This account is not allowed to access the admin panel.');
        }

        if ($allowedEmails === [] && ! app()->environment(['local', 'testing'])) {
            throw new AccessDeniedHttpException('Set ADMIN_EMAILS before enabling admin access outside local environments.');
        }
    }
}
