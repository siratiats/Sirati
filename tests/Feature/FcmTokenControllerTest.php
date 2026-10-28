<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FcmTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_persists_the_sha256_token_hash(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/fcm-tokens', [
            'token' => 'fcm-registration-token',
            'device_id' => 'device-one',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-registration-token',
            'token_hash' => hash('sha256', 'fcm-registration-token'),
        ]);
    }

    public function test_reregistering_the_same_token_updates_the_existing_row(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/fcm-tokens', [
            'token' => 'same-token',
            'device_id' => 'old-device',
        ])->assertOk();

        $this->postJson('/api/fcm-tokens', [
            'token' => 'same-token',
            'device_id' => 'new-device',
            'platform' => 'ios',
        ])->assertOk();

        $this->assertSame(1, UserFcmToken::query()->count());
        $this->assertDatabaseHas('user_fcm_tokens', [
            'token_hash' => hash('sha256', 'same-token'),
            'device_id' => 'new-device',
            'platform' => 'ios',
        ]);
    }

    public function test_two_different_tokens_create_two_rows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/fcm-tokens', ['token' => 'first-token'])->assertOk();
        $this->postJson('/api/fcm-tokens', ['token' => 'second-token'])->assertOk();

        $this->assertSame(2, UserFcmToken::query()->count());
        $this->assertNotSame(
            UserFcmToken::query()->where('token', 'first-token')->value('token_hash'),
            UserFcmToken::query()->where('token', 'second-token')->value('token_hash'),
        );
    }

    public function test_one_install_keeps_exactly_one_active_token_across_rotations_and_accounts(): void
    {
        $install = (string) Str::uuid();
        $users = User::factory()->count(2)->create();

        foreach (range(1, 6) as $rotation) {
            Sanctum::actingAs($users[$rotation % 2]);
            $this->postJson('/api/fcm-tokens', [
                'token' => "rotated-token-{$rotation}",
                'device_id' => $install,
            ])->assertOk();

            $active = UserFcmToken::query()
                ->where('device_id', $install)
                ->where('is_active', true)
                ->get();

            $this->assertCount(1, $active);
            $this->assertSame("rotated-token-{$rotation}", $active->first()->token);
            $this->assertSame($users[$rotation % 2]->id, $active->first()->user_id);
        }
    }

    public function test_separate_installs_of_one_user_stay_active(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/fcm-tokens', [
                'token' => "phone-{$i}-token",
                'device_id' => (string) Str::uuid(),
            ])->assertOk();
        }

        $this->assertSame(3, $user->fcmTokens()->where('is_active', true)->count());
    }

    public function test_shared_legacy_device_ids_never_deactivate_other_phones(): void
    {
        // Older builds sent the Android build id, which many phones share.
        $users = User::factory()->count(3)->create();

        foreach ($users as $i => $user) {
            Sanctum::actingAs($user);
            $this->postJson('/api/fcm-tokens', [
                'token' => "legacy-token-{$i}",
                'device_id' => 'TP1A.220624.014',
            ])->assertOk();
        }

        $this->assertSame(3, UserFcmToken::query()->where('is_active', true)->count());
    }

    public function test_token_longer_than_512_characters_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/fcm-tokens', [
            'token' => str_repeat('x', UserFcmToken::MAX_TOKEN_LENGTH + 1),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->assertDatabaseCount('user_fcm_tokens', 0);
    }

    public function test_destroy_removes_only_the_row_matching_the_token_hash(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => 'remove-me',
            'is_active' => true,
        ]);
        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => 'keep-me',
            'is_active' => true,
        ]);

        $this->deleteJson('/api/fcm-tokens', [
            'token' => 'remove-me',
        ])->assertOk();

        $this->assertDatabaseMissing('user_fcm_tokens', [
            'token_hash' => hash('sha256', 'remove-me'),
        ]);
        $this->assertDatabaseHas('user_fcm_tokens', [
            'token_hash' => hash('sha256', 'keep-me'),
        ]);
    }
}
