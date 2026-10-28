<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsMobileAuthPayload;
use App\Services\SocialAuth\SocialAccountLinker;
use App\Services\SocialAuth\SocialAuthProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile sign-in with a provider ID token (Google, later Apple). The token
 * is verified here, never trusted from the app; the answer has the same
 * shape as email login.
 */
class SocialAuthController extends Controller
{
    use BuildsMobileAuthPayload;

    public function __construct(
        private readonly SocialAuthProviders $providers,
        private readonly SocialAccountLinker $linker,
    ) {}

    public function signIn(Request $request, string $provider): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $identity = $this->providers->verifier($provider)
            ->verify($validated['id_token'], ['nonce' => $validated['nonce'] ?? null]);

        $user = $this->linker->userFor($identity);

        return response()->json($this->tokenPayload($user->loadMissing('jobTitle'), $validated['device_name'] ?? null));
    }
}
