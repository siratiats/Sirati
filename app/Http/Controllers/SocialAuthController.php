<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsMobileAuthPayload;
use App\Services\SocialAuth\AppleTokenService;
use App\Services\SocialAuth\SocialAccountLinker;
use App\Services\SocialAuth\SocialAuthProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobile sign-in with a provider ID token (Google, Apple). The token
 * is verified here, never trusted from the app; the answer has the same
 * shape as email login.
 */
class SocialAuthController extends Controller
{
    use BuildsMobileAuthPayload;

    public function __construct(
        private readonly SocialAuthProviders $providers,
        private readonly SocialAccountLinker $linker,
        private readonly AppleTokenService $apple,
    ) {}

    public function signIn(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['nullable', 'string', 'max:255'],
            'authorization_code' => [Rule::requiredIf($provider === 'apple'), 'nullable', 'string', 'max:2048'],
            'name' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        if ($provider === 'apple') {
            // Without the key we could never revoke the token later: refuse up front.
            $this->apple->assertConfigured();
        }

        $identity = $this->providers->verifier($provider)->verify($validated['id_token'], [
            'nonce' => $validated['nonce'] ?? null,
            'name' => $validated['name'] ?? null,
        ]);

        // Exchange before any account is touched, so a bad code changes nothing.
        $refreshToken = $provider === 'apple'
            ? $this->apple->exchange($validated['authorization_code'], (string) $identity->audience)
            : null;

        $user = $this->linker->userFor($identity);
        if ($refreshToken !== null) {
            $this->linker->rememberRefreshToken($identity, $refreshToken);
        }

        return response()->json($this->tokenPayload($user->loadMissing('jobTitle'), $validated['device_name'] ?? null));
    }

    /**
     * Sign in with Apple's web flow (Android) posts its result here; hand it
     * to the app through the intent the sign_in_with_apple plugin listens for.
     * Nothing is trusted or stored: the app sends the token to signIn().
     */
    public function appleCallback(Request $request): RedirectResponse
    {
        $params = array_filter(
            $request->only(['code', 'id_token', 'state', 'user', 'error']),
            fn ($value) => is_string($value) && strlen($value) <= 8192,
        );
        $package = preg_replace('/[^A-Za-z0-9._]/', '', (string) config('services.apple.android_package'));

        return redirect()->away(
            'intent://callback?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986)
                .'#Intent;package='.$package.';scheme=signinwithapple;end',
            307,
        );
    }
}
