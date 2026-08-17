<?php

namespace App\Http\Controllers;

use App\Models\MobileNotification;
use App\Models\NotificationDecision;
use App\Services\Notifications\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationEngagementController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function markOpened(Request $request, MobileNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        NotificationDecision::query()
            ->where('user_id', $request->user()->id)
            ->where('mobile_notification_id', $notification->id)
            ->whereNull('opened_at')
            ->update([
                'opened_at' => now(),
                'status' => 'opened',
            ]);

        return response()->json([
            'message' => 'تم تسجيل فتح الإشعار.',
            'data' => [
                'notification_id' => $notification->id,
                'read_at' => $notification->fresh()->read_at?->toISOString(),
            ],
        ]);
    }

    public function reportActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['nullable', 'string', Rule::in(['ar', 'en'])],
            'timezone_offset_minutes' => ['nullable', 'integer', 'between:-720,840'],
            'event' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $updates = [];
        if (isset($validated['language'])) {
            $updates['language'] = $validated['language'];
        }
        if (isset($validated['timezone_offset_minutes'])) {
            $updates['timezone_offset_minutes'] = $validated['timezone_offset_minutes'];
        }

        if ($updates !== []) {
            $this->preferences->update($user, $updates);
        }

        $preference = $this->preferences->touchActivity($user);

        return response()->json([
            'message' => 'تم تسجيل النشاط.',
            'data' => $this->preferences->toArray($preference),
        ]);
    }

    public function reportConversion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversion_type' => [
                'required',
                'string',
                Rule::in([
                    'analysis_started',
                    'analysis_completed',
                    'cv_generated',
                    'job_viewed',
                    'education_viewed',
                ]),
            ],
            'decision_id' => ['nullable', 'integer'],
            'notification_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;
        $query = NotificationDecision::query()->where('user_id', $userId);

        if (! empty($validated['decision_id'])) {
            $query->where('id', $validated['decision_id']);
        } elseif (! empty($validated['notification_id'])) {
            $query->where('mobile_notification_id', $validated['notification_id']);
        } else {
            $query->whereIn('status', ['accepted', 'opened'])
                ->whereNull('converted_at')
                ->latest('id')
                ->limit(1);
        }

        $decision = $query->first();
        if ($decision !== null && $decision->converted_at === null) {
            $decision->forceFill([
                'converted_at' => now(),
                'conversion_type' => $validated['conversion_type'],
                'status' => 'converted',
            ])->save();
        }

        $this->preferences->touchActivity($request->user());

        return response()->json([
            'message' => 'تم تسجيل التحويل.',
            'data' => [
                'decision_id' => $decision?->id,
                'conversion_type' => $validated['conversion_type'],
            ],
        ]);
    }
}
