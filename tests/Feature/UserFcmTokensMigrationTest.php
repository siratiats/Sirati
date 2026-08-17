<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserFcmTokensMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_is_idempotent_when_the_table_does_not_exist(): void
    {
        Schema::dropIfExists('user_fcm_tokens');

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertCorrectedShape();
    }

    public function test_migration_is_idempotent_with_the_corrected_shape_present(): void
    {
        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertCorrectedShape();
    }

    public function test_migration_backfills_a_populated_legacy_table_in_chunks(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 4096)->unique();
            $table->string('device_id')->nullable()->index();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['device_id', 'is_active']);
        });

        DB::table('user_fcm_tokens')->insert(
            collect(range(1, 501))
                ->map(fn (int $number): array => ['token' => 'legacy-token-'.$number])
                ->all()
        );

        $this->migration()->up();

        $this->assertCorrectedShape();
        $this->assertSame(
            hash('sha256', 'legacy-token-1'),
            DB::table('user_fcm_tokens')->where('token', 'legacy-token-1')->value('token_hash')
        );
        $this->assertSame(
            hash('sha256', 'legacy-token-501'),
            DB::table('user_fcm_tokens')->where('token', 'legacy-token-501')->value('token_hash')
        );
        $this->assertSame(
            501,
            DB::table('user_fcm_tokens')->whereNotNull('token_hash')->count()
        );
    }

    public function test_migration_can_be_rolled_back_and_applied_again(): void
    {
        $migration = $this->migration();

        $migration->down();

        $this->assertTrue(Schema::hasTable('user_fcm_tokens'));
        $this->assertFalse(Schema::hasColumn('user_fcm_tokens', 'token_hash'));

        $migration->up();

        $this->assertCorrectedShape();
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_07_28_000001_fix_user_fcm_tokens_token_index.php'
        );
    }

    private function assertCorrectedShape(): void
    {
        $this->assertTrue(Schema::hasTable('user_fcm_tokens'));
        foreach ([
            'id',
            'user_id',
            'token',
            'token_hash',
            'device_id',
            'platform',
            'app_version',
            'is_active',
            'last_seen_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('user_fcm_tokens', $column),
                "Expected user_fcm_tokens.{$column} to exist."
            );
        }
        $this->assertTrue(
            Schema::hasIndex('user_fcm_tokens', ['token_hash'], 'unique')
        );
        $this->assertFalse(
            Schema::hasIndex('user_fcm_tokens', ['token'], 'unique')
        );
        $this->assertTrue(
            Schema::hasIndex('user_fcm_tokens', ['device_id'])
        );
        $this->assertTrue(
            Schema::hasIndex('user_fcm_tokens', ['user_id', 'is_active'])
        );
        $this->assertTrue(
            Schema::hasIndex('user_fcm_tokens', ['device_id', 'is_active'])
        );
    }
}
