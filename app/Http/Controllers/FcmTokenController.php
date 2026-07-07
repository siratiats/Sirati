<?php

namespace App\Http\Controllers;

use App\Models\UserFcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $token = $validated['token'];

        if (($validated['device_id'] ?? null) !== null) {
            UserFcmToken::where('device_id', $validated['device_id'])
                ->where('user_id', '!=', $user->id)
                ->update([
                    'is_active' => false,
                    'last_seen_at' => now(),
                ]);
        }

        UserFcmToken::where('token', $token)
            ->where('user_id', '!=', $user->id)
            ->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);

        $fcmToken = UserFcmToken::updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'device_id' => $validated['device_id'] ?? null,
                'platform' => $validated['platform'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

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
            'token' => ['required', 'string', 'max:512'],
        ]);

        UserFcmToken::where('user_id', $request->user()->id)
            ->where('token', $validated['token'])
            ->update([
                'is_active' => false,
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'message' => 'FCM token deactivated successfully.',
        ]);
    }
}
