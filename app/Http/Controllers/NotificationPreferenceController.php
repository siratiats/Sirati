<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $preference = $this->preferences->forUser($request->user());

        return response()->json([
            'data' => $this->preferences->toArray($preference),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'language' => ['sometimes', 'string', Rule::in(['ar', 'en'])],
            'timezone_offset_minutes' => ['sometimes', 'integer', 'between:-720,840'],
            'preferred_time' => ['sometimes', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'quiet_hours_start' => ['sometimes', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'quiet_hours_end' => ['sometimes', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'max_per_week' => ['sometimes', 'integer', 'min:0', 'max:14'],
        ]);

        $preference = $this->preferences->update($request->user(), $validated);

        return response()->json([
            'message' => 'تم تحديث تفضيلات الإشعارات.',
            'data' => $this->preferences->toArray($preference),
        ]);
    }
}
