<?php

namespace App\Notifications;

use App\Services\ErrorReporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyEmailCode extends Notification implements ShouldQueue
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
        $minutes = (int) config('auth.verification.expire', 15);

        $data = [
            'name' => $notifiable->name ?? '',
            'code' => $this->code,
            'minutes' => $minutes,
            'actionUrl' => config('app.url'),
        ];

        // text() rebuilds viewData — pass the full payload once.
        return (new MailMessage)
            ->subject("{$appName} — تأكيد البريد الإلكتروني / Verify your email")
            ->view('emails.verify-email-code', $data)
            ->text('emails.verify-email-code-text', $data);
    }

    public function failed(Throwable $exception): void
    {
        app(ErrorReporter::class)->captureMailFailure(
            $exception,
            'verify_email_mail_failed',
            $this->userId,
        );

        Log::error('Queued email verification notification failed.', [
            'user_id' => $this->userId,
            'reason' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }
}
