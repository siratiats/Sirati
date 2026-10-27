<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Same rule as AdminController::authorizeAdmin: the signed-in email must be in ADMIN_EMAILS, and an
 * empty ADMIN_EMAILS only opens the panel in local/testing (fails closed everywhere else).
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
