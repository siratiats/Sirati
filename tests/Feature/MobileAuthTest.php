<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
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
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'created_at'],
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'salem@example.com']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'test-device']);
    }

    public function test_user_can_login_and_receive_token(): void
    {
        User::create([
            'name' => 'Salem Sayer',
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
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::create([
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'salem@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'salem@example.com',
        ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_authenticated_user_can_read_profile_and_logout(): void
    {
        $user = User::create([
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'salem@example.com');

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'تم تسجيل الخروج بنجاح.');
    }
}
