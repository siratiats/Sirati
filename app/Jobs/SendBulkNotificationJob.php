<?php

namespace App\Jobs;

use App\Models\NotificationCampaign;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one chunk of a broadcast campaign: persists a MobileNotification per user
 * and multicasts to their active tokens, then folds the delivery result back into
 * the parent NotificationCampaign. Dispatched once per chunk of user IDs.
 */
class SendBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Attempt each chunk once. A retry would re-run the notification-row insert
    // inside createAndSendToUsers and duplicate the in-app records, so hard
    // failures are recorded as failed on the campaign rather than retried.
    public int $tries = 1;

    /**
     * @param  array<int>  $userIds
     */
    public function __construct(
        private readonly array $userIds,
        private readonly string $title,
        private readonly string $body,
        private readonly string $type = 'info',
        private readonly ?string $actionType = null,
        private readonly ?string $actionUrl = null,
        private readonly ?int $campaignId = null,
    ) {}

    public function handle(FirebaseNotificationService $service): void
    {
        try {
            $result = $service->createAndSendToUsers(
                userIds: $this->userIds,
                title: $this->title,
                body: $this->body,
                type: $this->type,
                actionType: $this->actionType,
                actionUrl: $this->actionUrl,
            );

            $this->recordChunkResult(
                delivered: (int) ($result['successes'] ?? 0),
                failed: (int) ($result['failures'] ?? 0),
            );
        } catch (Throwable $exception) {
            // Count the whole chunk as failed so campaign totals stay consistent
            // and the campaign can still reach 'completed'. Not rethrown: retrying
            // would duplicate the notification rows inserted before the send.
            Log::error('[FCM Bulk Job] Chunk failed', [
                'campaign_id' => $this->campaignId,
                'users' => count($this->userIds),
                'error' => $exception->getMessage(),
            ]);

            $this->recordChunkResult(delivered: 0, failed: count($this->userIds));
        }
    }

    private function recordChunkResult(int $delivered, int $failed): void
    {
        if ($this->campaignId === null) {
            return;
        }

        $campaign = NotificationCampaign::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $campaign->increment('delivered', $delivered);
        $campaign->increment('failed', $failed);
        $campaign->increment('chunks_completed');

        $campaign->refresh();

        if ($campaign->chunks_completed >= $campaign->chunks_total && $campaign->status !== 'completed') {
            $campaign->update(['status' => 'completed']);
        }
    }
}
