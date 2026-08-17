<?php

namespace App\Notifications;

use App\Services\ErrorReporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasswordResetCode extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Retry after 1, 5, 15, and 30 minutes.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800];

    public function __construct(
        public readonly string $code,
        public readonly int $userId,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Sirati');
        $minutes = min(
            (int) config('auth.passwords.users.expire', 60),
            (int) config('auth.verification.expire', 15),
        );

        $data = [
            'name' => $notifiable->name ?? '',
            'code' => $this->code,
            'minutes' => $minutes,
            'actionUrl' => config('app.url'),
        ];

        return (new MailMessage)
            ->subject("{$appName} — استعادة كلمة المرور / Password reset")
            ->view('emails.password-reset-code', $data)
            ->text('emails.password-reset-code-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        app(ErrorReporter::class)->captureMailFailure(
            $exception,
            'password_reset_mail_failed',
            $this->userId,
        );

        Log::error('Queued password reset notification failed.', [
            'user_id' => $this->userId,
            'reason' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
