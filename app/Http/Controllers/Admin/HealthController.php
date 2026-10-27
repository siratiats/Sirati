<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardMetrics;
use App\Services\Admin\QueueInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use AuthorizesAdmin;

    public function index(Request $request, QueueInspector $queue, DashboardMetrics $metrics)
    {
        $this->authorizeAdmin($request);

        $since = now()->subDay();
        $now = now();

        return view('admin.health', [
            'database' => $this->databaseStatus(),
            'queue' => $queue->summary(),
            'failedJobs' => $queue->failedJobs(50),
            'pdfFailures' => $metrics->attempt(fn () => $queue->failedJobs(100, 'RenderCvPdfJob', $since), null),
            'ai' => $metrics->attempt(fn () => $metrics->aiUsage($since, $now), ['available' => false]),
            'recentErrors' => $metrics->attempt(fn () => $metrics->recentFailures(15), null),
            'checkedAt' => $now,
        ]);
    }

    public function retry(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->runQueueCommand('queue:retry', ['id' => [$id]], "Failed job {$id} was pushed back onto its queue.");
    }

    public function forget(Request $request, string $id): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->runQueueCommand('queue:forget', ['id' => $id], "Failed job {$id} was deleted.");
    }

    public function retryAll(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        return $this->runQueueCommand('queue:retry', ['id' => ['all']], 'All failed jobs were pushed back onto their queues.');
    }

    private function runQueueCommand(string $command, array $arguments, string $success): RedirectResponse
    {
        try {
            $exitCode = Artisan::call($command, $arguments);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.health.index')->with('error', 'The queue command failed: '.class_basename($exception));
        }

        $output = trim(Artisan::output());
        $lower = strtolower($output);

        if (str_contains($lower, 'no retryable jobs')) {
            return redirect()->route('admin.health.index')->with('status', 'There are no failed jobs to retry.');
        }

        if ($exitCode !== 0 || str_contains($lower, 'no failed job') || str_contains($lower, 'unable to find')) {
            return redirect()->route('admin.health.index')->with('error', $output !== '' ? QueueInspector::firstLine($output) : 'The queue command failed.');
        }

        return redirect()->route('admin.health.index')->with('status', $success);
    }

    /**
     * @return array{ok: bool, latency_ms: ?float, driver: ?string}
     */
    private function databaseStatus(): array
    {
        try {
            $started = hrtime(true);
            DB::connection()->select('select 1');

            return [
                'ok' => true,
                'latency_ms' => round((hrtime(true) - $started) / 1_000_000, 1),
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'latency_ms' => null, 'driver' => null];
        }
    }
}
