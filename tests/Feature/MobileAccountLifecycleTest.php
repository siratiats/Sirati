<?php

namespace Tests\Feature;

use App\Models\CvAnalysis;
use App\Models\GeneratedCv;
use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword1',
            'password_confirmation' => 'newpassword1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_updates_password_and_revokes_other_tokens(): void
    {
        $user = $this->makeUser();
        $keepToken = $user->createToken('keep-device');
        $otherToken = $user->createToken('other-device');

        $this->withToken($keepToken->plainTextToken)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password123',
                'password' => 'newpassword1',
                'password_confirmation' => 'newpassword1',
            ])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword1', $user->password));
        $this->assertFalse(Hash::check('password123', $user->password));

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $keepToken->accessToken->id,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        // Old password no longer works for login.
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'newpassword1',
        ])->assertOk();
    }

    public function test_update_profile_updates_name_only(): void
    {
        $user = $this->makeUser(['email' => 'keep@example.com']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'New Name',
            'email' => 'attacker@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'keep@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'keep@example.com',
        ]);
    }

    public function test_delete_account_rejects_wrong_password(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/account', [
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_removes_user_and_owned_content(): void
    {
        $user = $this->makeUser();
        $userId = $user->id;

        CvAnalysis::query()->create([
            'user_id' => $userId,
            'target_job_title' => 'Developer',
            'input_method' => 'paste',
            'resume_text' => 'Sample resume text',
            'score_total' => 70,
            'grade' => 'B',
            'job_match' => 65,
            'criteria' => [],
            'strengths' => [],
            'weaknesses' => [],
            'keywords_found' => [],
            'keywords_missing' => [],
            'quick_wins' => [],
        ]);

        GeneratedCv::query()->create([
            'user_id' => $userId,
            'full_name' => 'Salem Sayer',
            'target_job_title' => 'Developer',
            'skills_input' => 'PHP',
            'experience_input' => 'Laravel work',
            'education_input' => 'BSc',
            'generated_markdown' => '# CV',
            'form_payload' => ['full_name' => 'Salem Sayer'],
        ]);

        UserFcmToken::query()->create([
            'user_id' => $userId,
            'token' => 'fcm-test-token',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $token = $user->createToken('device');

        $this->withToken($token->plainTextToken)
            ->deleteJson('/api/auth/account', [
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف الحساب نهائياً.');

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertSame(0, CvAnalysis::query()->where('user_id', $userId)->count());
        $this->assertSame(0, GeneratedCv::query()->where('user_id', $userId)->count());
        $this->assertSame(0, UserFcmToken::query()->where('user_id', $userId)->count());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $userId,
        ]);
        // Orphan rows with null user_id must not remain either.
        $this->assertSame(0, CvAnalysis::query()->whereNull('user_id')->count());
        $this->assertSame(0, GeneratedCv::query()->whereNull('user_id')->count());
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        // Array cache persists across tests in-process — flush so this case is isolated.
        cache()->flush();

        User::create([
            'name' => 'Rate Limit',
            'email' => 'rate@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'rate@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'rate@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_register_accepts_phone_and_location(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'With Phone',
            'email' => 'phone@example.com',
            'phone' => '0500000000',
            'location' => 'Riyadh',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.phone', '0500000000')
            ->assertJsonPath('data.user.location', 'Riyadh');

        $this->assertDatabaseHas('users', [
            'email' => 'phone@example.com',
            'phone' => '0500000000',
            'location' => 'Riyadh',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        $verifiedAt = $overrides['email_verified_at'] ?? now();
        unset($overrides['email_verified_at']);

        $user = User::create(array_merge([
            'name' => 'Salem Sayer',
            'email' => 'salem@example.com',
            'password' => Hash::make('password123'),
        ], $overrides));

        // Not mass-assignable — set explicitly for API tests that need verified access.
        $user->forceFill(['email_verified_at' => $verifiedAt])->save();

        return $user->fresh();
    }
}
