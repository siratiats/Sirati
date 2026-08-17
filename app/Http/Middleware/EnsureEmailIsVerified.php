<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Block API access until the authenticated user has verified their email.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            return response()->json([
                'message' => 'يجب تأكيد البريد الإلكتروني أولاً.',
                'code' => 'email_not_verified',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
