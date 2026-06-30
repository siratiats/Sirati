<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seed_command_creates_user(): void
    {
        $this->artisan('admin:seed', [
            'email' => 'admin@sirati.test',
            '--name' => 'Sirati Admin',
            '--password' => 'password123',
            '--no-env' => true,
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@sirati.test')->firstOrFail();

        $this->assertSame('Sirati Admin', $admin->name);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    public function test_admin_seed_command_updates_existing_user(): void
    {
        User::factory()->create([
            'email' => 'admin@sirati.test',
            'name' => 'Old Name',
            'password' => Hash::make('old-password'),
        ]);

        $this->artisan('admin:seed', [
            'email' => 'admin@sirati.test',
            '--name' => 'Updated Admin',
            '--password' => 'new-password',
            '--no-env' => true,
        ])->assertSuccessful();

        $admin = User::where('email', 'admin@sirati.test')->firstOrFail();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertTrue(Hash::check('new-password', $admin->password));
        $this->assertSame(1, User::where('email', 'admin@sirati.test')->count());
    }

    public function test_admin_seed_command_rejects_short_password(): void
    {
        $this->artisan('admin:seed', [
            'email' => 'admin@sirati.test',
            '--password' => 'short',
            '--no-env' => true,
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@sirati.test']);
    }
}
