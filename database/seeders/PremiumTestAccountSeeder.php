<?php

namespace Database\Seeders;

use App\Models\SubscriptionAuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Grants time-bound premium to a named test account for pre-launch validation.
 *
 * Deliberately NOT registered in DatabaseSeeder — `php artisan db:seed` must
 * never grant premium as a side effect. Run it explicitly:
 *
 *   php artisan db:seed --class=PremiumTestAccountSeeder
 *
 * Refuses to run in production (AGENTS.md rule 7 — a grant path that can be
 * triggered by an environment must fail closed outside development). To grant
 * premium in production, use the audited admin route:
 *
 *   POST /admin/users/{user}/grant-premium
 *
 * Override the defaults from the environment when granting to other testers:
 *   SEED_PREMIUM_EMAIL=tester@example.com SEED_PREMIUM_MONTHS=1 \
 *     php artisan db:seed --class=PremiumTestAccountSeeder
 */
class PremiumTestAccountSeeder extends Seeder
{
    private const DEFAULT_EMAIL = 'bkryelmaki30@gmail.com';

    private const DEFAULT_MONTHS = 6;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error(
                'PremiumTestAccountSeeder refuses to run in production. '
                .'Use POST /admin/users/{user}/grant-premium instead — it is audited.'
            );

            return;
        }

        $email = (string) env('SEED_PREMIUM_EMAIL', self::DEFAULT_EMAIL);
        $months = (int) env('SEED_PREMIUM_MONTHS', self::DEFAULT_MONTHS);
        $expiresAt = now()->addMonths(max(1, $months));

        $user = User::query()->where('email', $email)->first();
        $created = false;

        if ($user === null) {
            $user = User::query()->create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $created = true;
        }

        $previous = $user->premium_until;

        // premium_until is the entitlement of record. `is_premium` is the legacy
        // boolean being retired — do not write it, or it outlives the column.
        // These fields are intentionally absent from $fillable, hence forceFill.
        $user->forceFill([
            'premium_until' => $expiresAt,
            'subscription_plan_id' => 'internal_test',
            'subscription_provider' => 'admin_override',
            'subscription_external_id' => null,
        ])->save();

        SubscriptionAuditLog::create([
            'user_id' => $user->id,
            'admin_id' => null,
            'action' => 'seeder_grant',
            'reason' => 'Pre-launch validation grant via PremiumTestAccountSeeder',
            'previous_premium_until' => $previous,
            'new_premium_until' => $expiresAt,
            'metadata' => [
                'environment' => app()->environment(),
                'seeder' => self::class,
                'account_created' => $created,
            ],
        ]);

        $this->command->info(
            ($created ? 'Created and granted' : 'Granted')
            ." premium to {$email} until {$expiresAt->toDayDateTimeString()}."
        );

        if ($created) {
            $this->command->warn(
                "Account {$email} did not exist and was created with a random password. "
                .'Use the forgot-password flow to set one.'
            );
        }

        $this->command->line('Revoke with: POST /admin/users/'.$user->id.'/revoke-premium');
    }
}
