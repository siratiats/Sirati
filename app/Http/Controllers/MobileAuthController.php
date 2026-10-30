<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsMobileAuthPayload;
use App\Models\JobTitle;
use App\Models\User;
use App\Services\CvTemplateRenderer;
use App\Services\EmailVerificationService;
use App\Services\I18n\ServerText;
use App\Services\PasswordResetService;
use App\Services\SocialAuth\AppleTokenService;
use App\Services\SocialAuth\SocialAccountLinker;
use App\Services\SocialAuth\SocialAuthProviders;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    use BuildsMobileAuthPayload;

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
                'email' => ServerText::t('serverAuthInvalidCredentials'),
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
            'message' => ServerText::t('serverAuthEmailVerified'),
            'data' => $this->userPayload($user->fresh()->loadMissing('jobTitle')),
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => ServerText::t('serverAuthEmailAlreadyVerified'),
                'data' => $this->userPayload($user->loadMissing('jobTitle')),
            ]);
        }

        $this->emailVerification->send($user);

        return response()->json([
            'message' => ServerText::t('serverAuthVerificationCodeSent'),
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
            'message' => ServerText::t('serverAuthResetCodeSent'),
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
                'code' => ServerText::t('serverAuthInvalidCode'),
            ]);
        }

        $this->passwordReset->reset(
            $user,
            $validated['code'],
            $validated['password'],
        );

        return response()->json([
            'message' => ServerText::t('serverAuthPasswordReset'),
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
            'message' => ServerText::t('serverAuthLoggedOut'),
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
                'current_password' => ServerText::t('serverAuthCurrentPasswordWrong'),
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        $currentId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
            ->delete();

        return response()->json([
            'message' => ServerText::t('serverAuthPasswordChanged'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->jobTitleRules($request) + [
                'name' => ['required', 'string', 'max:255'],
                'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            ],
            $this->jobTitleMessages(),
        );

        /** @var User $user */
        $user = $request->user();
        $user->name = $validated['name'];

        // Email and phone stay read-only here; location is editable but, like
        // the job title, only changes when the client sends it.
        if (array_key_exists('location', $validated)) {
            $location = trim((string) $validated['location']);
            $user->location = $location === '' ? null : $location;
        }

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

    /**
     * Profile photo, shown in the app and on the CV header. The app crops it
     * square before upload; anything else is stored as sent.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120', 'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $previous = $user->avatar_path;

        $file = $validated['avatar'];
        $path = $file->storeAs(
            'avatars',
            Str::uuid()->toString().'.'.$file->extension(),
            'public',
        );
        if ($path === false) {
            abort(500, ServerText::t('serverAuthAvatarSaveFailed'));
        }

        $user->forceFill(['avatar_path' => $path])->save();
        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json([
            'data' => $this->userPayload($user->fresh()->loadMissing('jobTitle')),
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return response()->json([
            'data' => $this->userPayload($user->fresh()->loadMissing('jobTitle')),
        ]);
    }

    public function deleteAccount(Request $request, SocialAuthProviders $providers, SocialAccountLinker $linker): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasPassword()) {
            $validated = $request->validate([
                'password' => ['required', 'string'],
            ]);

            if (! Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'password' => ServerText::t('serverAuthPasswordWrong'),
                ]);
            }
        } else {
            // Google/Apple-only accounts confirm by signing in with the provider again.
            $validated = $request->validate([
                'provider' => ['required', 'string', Rule::in(SocialAuthProviders::names())],
                'id_token' => ['required', 'string', 'max:8192'],
                'nonce' => ['nullable', 'string', 'max:255'],
            ]);

            $identity = $providers->verifier($validated['provider'])
                ->verify($validated['id_token'], ['nonce' => $validated['nonce'] ?? null]);

            if ($linker->linkedUser($identity)?->is($user) !== true) {
                throw ValidationException::withMessages([
                    'id_token' => ServerText::t('serverAuthSocialAccountMismatch'),
                ]);
            }
        }

        // Hard-delete owned content first. CV tables use nullOnDelete FKs, so
        // without this step rows would remain with user_id = null (privacy risk).
        $user->cvAnalyses()->delete();
        $pdfRenderer = app(CvTemplateRenderer::class);
        $cvIds = $user->generatedCvs()->pluck('id');
        foreach ($cvIds as $cvId) {
            $pdfRenderer->deletePdfArtifacts($cvId);
        }
        $user->generatedCvs()->delete();
        // Close the interval in which an already-running renderer could publish
        // between the pre-delete cleanup and the mass delete (no model events).
        foreach ($cvIds as $cvId) {
            $pdfRenderer->deletePdfArtifacts($cvId);
        }
        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
        }
        $user->mobileNotifications()->delete();
        $user->fcmTokens()->delete();
        $user->tokens()->delete();
        // App Store 5.1.1(v): deleting the account revokes Sign in with Apple.
        $apple = app(AppleTokenService::class);
        foreach ($user->socialAccounts()->where('provider', 'apple')->whereNotNull('refresh_token')->get() as $account) {
            $apple->revoke($account->refresh_token, (string) $account->client_id);
        }
        $user->delete();

        return response()->json([
            'message' => ServerText::t('serverAuthAccountDeleted'),
        ]);
    }

    /**
     * @return array<string, list<ValidationRule|string>>
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
            'job_title_other.required' => ServerText::t('serverAuthJobTitleOtherRequired'),
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
}
