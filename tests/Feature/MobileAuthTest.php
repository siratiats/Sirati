<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Notifications\VerifyEmailCode;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token_and_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'test-device',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'salem@example.com')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'email_verified', 'created_at'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'salem@example.com',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'test-device']);

        $user = User::where('email', 'salem@example.com')->first();
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'salem@example.com',
            'password' => 'password123',
            'device_name' => 'test-device',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', 'salem@example.com')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_of_unverified_user_returns_token_and_sends_code(): void
    {
        Notification::fake();

        User::factory()->unverified()->create([
            'email' => 'pending@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
            'device_name' => 'test-device',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $user = User::where('email', 'pending@example.com')->first();
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'salem@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_verify_email_with_code(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'verify@example.com',
            'password' => Hash::make('password123'),
        ]);

        $service = app(\App\Services\EmailVerificationService::class);
        $service->send($user, force: true);

        $code = Cache::get($service->cacheKey($user));
        $this->assertNotEmpty($code);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verify', [
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('message', 'تم تأكيد البريد الإلكتروني بنجاح.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verify_email_rejects_invalid_code(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verify', [
            'code' => '000000',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_resend_verification_sends_new_code(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/resend')
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_unverified_user_cannot_access_protected_routes(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonPath('email_verified', false);

        // Session endpoints remain available.
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_user_can_request_password_reset_code(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'salem@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, PasswordResetCode::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('device')->plainTextToken;

        $service = app(PasswordResetService::class);
        $service->send($user, force: true);
        $code = Cache::get($service->cacheKey($user));
        $this->assertNotEmpty($code);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'code' => $code,
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword1', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);

        // Old token no longer works.
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'newpassword1',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_code(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'reset@example.com',
            'code' => '000000',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_authenticated_user_can_read_profile_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'salem@example.com')
            ->assertJsonPath('data.email_verified', true);

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'تم تسجيل الخروج بنجاح.');
    }
}
