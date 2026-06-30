<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedAdminUser extends Command
{
    protected $signature = 'admin:seed
        {email : Admin email address}
        {--name=Admin : Admin display name}
        {--password= : Admin password; omit to enter it securely}
        {--no-env : Do not update ADMIN_EMAILS in .env}';

    protected $description = 'Create or update an admin user and add the email to ADMIN_EMAILS.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name')) ?: 'Admin';
        $password = (string) ($this->option('password') ?: $this->secret('Admin password'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('The admin email address is invalid.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('The admin password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info($user->wasRecentlyCreated ? 'Admin user created.' : 'Admin user updated.');

        if (! $this->option('no-env')) {
            $this->syncAdminEmail($email);
        }

        $this->line("Admin email: {$email}");
        $this->line('Login route: /admin/login');
        $this->warn('If config is cached in production, run: php artisan config:clear');

        return self::SUCCESS;
    }

    private function syncAdminEmail(string $email): void
    {
        $envPath = base_path('.env');

        if (! is_file($envPath) || ! is_writable($envPath)) {
            $this->warn('.env is not writable. Add this email to ADMIN_EMAILS manually.');

            return;
        }

        $contents = (string) file_get_contents($envPath);
        $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $emails = $this->adminEmailsFromEnv($contents);

        if (! in_array($email, $emails, true)) {
            $emails[] = $email;
        }

        $updatedLine = 'ADMIN_EMAILS='.implode(',', $emails);

        if (preg_match('/^ADMIN_EMAILS=.*$/m', $contents) === 1) {
            $contents = preg_replace('/^ADMIN_EMAILS=.*$/m', $updatedLine, $contents) ?? $contents;
        } else {
            $contents = rtrim($contents).$lineEnding.$updatedLine.$lineEnding;
        }

        file_put_contents($envPath, $contents);

        $this->info('ADMIN_EMAILS updated in .env.');
    }

    /**
     * @return list<string>
     */
    private function adminEmailsFromEnv(string $contents): array
    {
        if (preg_match('/^ADMIN_EMAILS=(.*)$/m', $contents, $matches) !== 1) {
            return [];
        }

        $value = trim($matches[1]);
        $value = trim($value, '"\'');

        return array_values(array_unique(array_filter(array_map(
            fn (string $email): string => Str::lower(trim($email)),
            explode(',', $value),
        ))));
    }
}
