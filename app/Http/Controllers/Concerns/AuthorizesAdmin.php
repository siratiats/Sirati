<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Admin\AdminAllowList;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Admin gate shared by every admin controller. The allow-list itself, and its
 * fail-closed behaviour, live in AdminAllowList.
 */
trait AuthorizesAdmin
{
    protected function authorizeAdmin(Request $request): void
    {
        if (! app(AdminAllowList::class)->allows($request->user()?->email)) {
            throw new AccessDeniedHttpException('This account is not allowed to access the admin panel.');
        }
    }
}
