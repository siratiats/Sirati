<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FcmTokenController extends Controller
{
    public function store(
        Request $request,
        NotificationPreferenceService $preferences,
    ): JsonResponse {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:'.UserFcmToken::MAX_TOKEN_LENGTH],
            'device_id' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', Rule::in(['ar', 'en'])],
            'timezone_offset_minutes' => ['nullable', 'integer', 'between:-720,840'],
            'notifications_enabled' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $token = $validated['token'];
        $tokenHash = UserFcmToken::hashToken($token);

        if (($validated['device_id'] ?? null) !== null) {
            UserFcmToken::where('device_id', $validated['device_id'])
                ->where('user_id', '!=', $user->id)
                ->update([
                    'is_active' => false,
                    'last_seen_at' => now(),
                ]);
        }

        UserFcmToken::where('token_hash', $tokenHash)
            ->where('user_id', '!=', $user->id)
            ->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);

        $fcmToken = UserFcmToken::updateOrCreate(
            ['token_hash' => $tokenHash],
            [
                'token' => $token,
                'user_id' => $user->id,
                'device_id' => $validated['device_id'] ?? null,
                'platform' => $validated['platform'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        // Keep server-side preference aligned with the device so automation
        // respects opt-out even after reinstall/re-login token re-register.
        $prefUpdates = ['last_active_at' => now()];
        if (array_key_exists('language', $validated) && $validated['language'] !== null) {
            $prefUpdates['language'] = $validated['language'];
        }
        if (array_key_exists('timezone_offset_minutes', $validated)
            && $validated['timezone_offset_minutes'] !== null) {
            $prefUpdates['timezone_offset_minutes'] = $validated['timezone_offset_minutes'];
        }
        if (array_key_exists('notifications_enabled', $validated)
            && $validated['notifications_enabled'] !== null) {
            $prefUpdates['enabled'] = (bool) $validated['notifications_enabled'];
        }
        $preferences->update($user, $prefUpdates);

        return response()->json([
            'message' => 'FCM token registered successfully.',
            'data' => [
                'id' => $fcmToken->id,
                'device_id' => $fcmToken->device_id,
                'platform' => $fcmToken->platform,
                'is_active' => $fcmToken->is_active,
                'last_seen_at' => $fcmToken->last_seen_at?->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:'.UserFcmToken::MAX_TOKEN_LENGTH],
            // When true, also persists server-side opt-out (Settings toggle).
            // Logout/token-refresh deactivation must NOT permanently opt the user out.
            'opt_out' => ['sometimes', 'boolean'],
        ]);

        UserFcmToken::where('user_id', $request->user()->id)
            ->where('token_hash', UserFcmToken::hashToken($validated['token']))
            ->delete();

        if ($request->boolean('opt_out')) {
            app(NotificationPreferenceService::class)
                ->update($request->user(), ['enabled' => false]);
        }

        return response()->json([
            'message' => 'FCM token removed successfully.',
        ]);
    }
}
