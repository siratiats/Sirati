<?php

namespace App\Http\Controllers;

use App\Models\JobTitle;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $emailVerification,
        private readonly PasswordResetService $passwordReset,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->jobTitleRules($request) + [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:30'],
                'location' => ['nullable', 'string', 'max:120'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'device_name' => ['nullable', 'string', 'max:120'],
            ],
            $this->jobTitleMessages(),
        );

        [$jobTitleId, $jobTitleOther] = $this->normalizedJobTitleFields($validated);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'location' => $validated['location'] ?? null,
            'job_title_id' => $jobTitleId,
            'job_title_other' => $jobTitleOther,
            'password' => $validated['password'],
        ]);

        // New accounts must verify email before using protected features.
        // Queue-dispatch failures must not roll back account creation. SMTP
        // transport failures are retried by the queued notification instead.
        try {
            $this->emailVerification->send($user, force: true);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($this->tokenPayload($user->loadMissing('jobTitle'), $validated['device_name'] ?? null), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'بيانات تسجيل الدخول غير صحيحة.',
            ]);
        }

        // Unverified users still get a token so the app can complete verification.
        // Protected APIs remain blocked until email_verified_at is set.
        if (! $user->hasVerifiedEmail()) {
            try {
                $this->emailVerification->send($user);
            } catch (ValidationException) {
                // Resend throttled — user can still verify with the previous code.
            }
        }

        return response()->json($this->tokenPayload($user->loadMissing('jobTitle'), $validated['device_name'] ?? null));
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->emailVerification->verify($user, $validated['code']);

        return response()->json([
            'message' => 'تم تأكيد البريد الإلكتروني بنجاح.',
            'data' => $this->userPayload($user->fresh()->loadMissing('jobTitle')),
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'البريد الإلكتروني مؤكد مسبقاً.',
                'data' => $this->userPayload($user->loadMissing('jobTitle')),
            ]);
        }

        $this->emailVerification->send($user);

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Same response whether the email exists — avoid account enumeration.
        if ($user) {
            try {
                $this->passwordReset->send($user);
            } catch (ValidationException $e) {
                // Surface throttle only when the account is real.
                throw $e;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => 'تم إرسال رمز استعادة كلمة المرور إذا كان البريد مسجلاً لدينا.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ]);
        }

        $this->passwordReset->reset(
            $user,
            $validated['code'],
            $validated['password'],
        );

        return response()->json([
            'message' => 'تم تعيين كلمة المرور الجديدة بنجاح. يمكنك تسجيل الدخول الآن.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userPayload($request->user()->loadMissing('jobTitle')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'كلمة المرور الحالية غير صحيحة.',
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->jobTitleRules($request) + [
                'name' => ['required', 'string', 'max:255'],
            ],
            $this->jobTitleMessages(),
        );

        /** @var User $user */
        $user = $request->user();
        $user->name = $validated['name'];

        // Only touch job title fields when the client sends them — older app
        // builds only patch the name and must not wipe existing targeting data.
        if (array_key_exists('job_title_id', $validated) || array_key_exists('job_title_other', $validated)) {
            [$jobTitleId, $jobTitleOther] = $this->normalizedJobTitleFields($validated);
            $user->job_title_id = $jobTitleId;
            $user->job_title_other = $jobTitleOther;
        }

        $user->save();

        return response()->json([
            'data' => $this->userPayload($user->fresh()->loadMissing('jobTitle')),
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        // Hard-delete owned content first. CV tables use nullOnDelete FKs, so
        // without this step rows would remain with user_id = null (privacy risk).
        $user->cvAnalyses()->delete();
        $user->generatedCvs()->delete();
        $user->mobileNotifications()->delete();
        $user->fcmTokens()->delete();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'تم حذف الحساب نهائياً.',
        ]);
    }

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    private function jobTitleRules(Request $request): array
    {
        return [
            'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'job_title_other' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn (): bool => $this->selectedJobTitleIsOther($request->input('job_title_id'))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function jobTitleMessages(): array
    {
        return [
            'job_title_other.required' => 'يرجى كتابة المسمى الوظيفي عند اختيار أخرى.',
        ];
    }

    private function selectedJobTitleIsOther(mixed $jobTitleId): bool
    {
        if ($jobTitleId === null || $jobTitleId === '') {
            return false;
        }

        return JobTitle::query()
            ->whereKey($jobTitleId)
            ->where('slug', 'other')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int|null, 1: string|null}
     */
    private function normalizedJobTitleFields(array $validated): array
    {
        $jobTitleId = isset($validated['job_title_id']) ? (int) $validated['job_title_id'] : null;
        $jobTitleOther = isset($validated['job_title_other'])
            ? trim((string) $validated['job_title_other'])
            : null;

        if ($jobTitleOther === '') {
            $jobTitleOther = null;
        }

        if ($jobTitleId === null) {
            return [null, null];
        }

        $title = JobTitle::query()->find($jobTitleId);
        if (! $title || ! $title->isOther()) {
            $jobTitleOther = null;
        }

        return [$jobTitleId, $jobTitleOther];
    }

    private function tokenPayload(User $user, ?string $deviceName): array
    {
        $token = $user->createToken($deviceName ?: 'sirati-mobile')->plainTextToken;

        return [
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ],
        ];
    }

    private function userPayload(User $user): array
    {
        $jobTitle = $user->relationLoaded('jobTitle') ? $user->jobTitle : $user->jobTitle()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location,
            'job_title_id' => $user->job_title_id,
            'job_title_other' => $user->job_title_other,
            'job_title' => $jobTitle ? [
                'id' => $jobTitle->id,
                'slug' => $jobTitle->slug,
                'name_ar' => $jobTitle->name_ar,
                'name_en' => $jobTitle->name_en,
                'category' => $jobTitle->category,
            ] : null,
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
