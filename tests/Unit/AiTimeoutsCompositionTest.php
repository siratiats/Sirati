<?php

namespace Tests\Unit;

use App\Jobs\GenerateCvAdviceJob;
use App\Jobs\GenerateCvContentJob;
use App\Services\Ai\AiTimeouts;
use App\Services\Ai\Schemas\AnalysisAdviceSchema;
use App\Services\Ai\Schemas\ClassifyDocumentSchema;
use App\Services\Ai\Schemas\EnhanceCvFieldSchema;
use App\Services\Ai\Schemas\EnhanceJobDescriptionSchema;
use App\Services\Ai\Schemas\GenerateCvSchema;
use App\Services\Ai\Schemas\JobMatchAdviceSchema;
use App\Services\Ai\Schemas\OperationSchemas;
use App\Support\QueuedJobTimeouts;
use Tests\TestCase;

class AiTimeoutsCompositionTest extends TestCase
{
    public function test_http_plus_fallback_budget_is_below_job_timeout_which_is_below_client_poll(): void
    {
        $this->assertLessThan(
            AiTimeouts::JOB_SECONDS,
            AiTimeouts::httpPlusFallbackBudgetSeconds(),
            'OpenAI HTTP budget + DeepInfra fallback budget must fit inside one job attempt.',
        );
        $this->assertLessThan(
            AiTimeouts::CLIENT_POLL_SECONDS,
            AiTimeouts::JOB_SECONDS,
            'The client poll must outlive one full job attempt so it does not time out while the worker is still running.',
        );
        $this->assertSame(AiTimeouts::JOB_SECONDS, (new GenerateCvContentJob(1))->timeout);
        $this->assertSame(AiTimeouts::JOB_SECONDS, (new GenerateCvAdviceJob(1))->timeout);
    }

    public function test_one_provider_attempt_fits_inside_its_budget(): void
    {
        $this->assertLessThanOrEqual(
            AiTimeouts::HTTP_BUDGET_SECONDS,
            (int) config('services.openai.timeout', 30),
        );
        $this->assertLessThanOrEqual(
            AiTimeouts::HTTP_BUDGET_SECONDS,
            (int) config('services.anthropic.timeout', 30),
        );
        $this->assertLessThanOrEqual(
            AiTimeouts::FALLBACK_BUDGET_SECONDS,
            (int) config('services.deepinfra.timeout', 45),
        );
        $this->assertLessThanOrEqual(
            AiTimeouts::FALLBACK_BUDGET_SECONDS,
            (int) config('services.deepinfra.generate_timeout', AiTimeouts::DEEPINFRA_GENERATE_TIMEOUT),
        );
        $this->assertGreaterThan(
            AiTimeouts::DEEPINFRA_GENERATE_TIMEOUT,
            AiTimeouts::FALLBACK_BUDGET_SECONDS,
            'DeepInfra generate hang must not be able to start a second full-length attempt.',
        );
    }

    public function test_worst_case_job_attempt_fits_inside_job_timeout(): void
    {
        $this->assertLessThan(
            AiTimeouts::JOB_SECONDS,
            AiTimeouts::worstCaseJobAttemptSeconds(),
            'Each layer can overrun its budget by one sleep; that total must still fit in one job attempt.',
        );
    }

    public function test_layer_worst_case_is_budget_plus_one_sleep_when_attempt_fits(): void
    {
        $this->assertSame(
            AiTimeouts::FALLBACK_BUDGET_SECONDS + AiTimeouts::httpSleepOverrunSeconds(),
            AiTimeouts::layerWorstCaseSeconds(
                AiTimeouts::FALLBACK_BUDGET_SECONDS,
                AiTimeouts::DEEPINFRA_GENERATE_TIMEOUT,
            ),
        );
        $this->assertSame(
            AiTimeouts::HTTP_BUDGET_SECONDS + AiTimeouts::httpSleepOverrunSeconds(),
            AiTimeouts::layerWorstCaseSeconds(
                AiTimeouts::HTTP_BUDGET_SECONDS,
                (int) config('services.openai.timeout', 30),
            ),
        );
    }

    public function test_layer_worst_case_is_the_attempt_when_it_exceeds_the_budget(): void
    {
        $budget = AiTimeouts::FALLBACK_BUDGET_SECONDS;
        $attempt = $budget + 50;

        $this->assertSame($attempt, AiTimeouts::layerWorstCaseSeconds($budget, $attempt));
        $this->assertGreaterThanOrEqual(
            AiTimeouts::JOB_SECONDS,
            AiTimeouts::layerWorstCaseSeconds($budget, $attempt)
                + AiTimeouts::layerWorstCaseSeconds(
                    AiTimeouts::HTTP_BUDGET_SECONDS,
                    (int) config('services.openai.timeout', 30),
                ),
            'An attempt timeout above the fallback budget must be able to kill the job mid-flight — /up has to reject that live config.',
        );
    }

    public function test_every_queued_job_declares_an_explicit_timeout(): void
    {
        $jobs = QueuedJobTimeouts::jobClasses();
        $this->assertNotEmpty($jobs);

        foreach ($jobs as $class) {
            $timeout = QueuedJobTimeouts::timeoutSeconds($class);
            $this->assertGreaterThan(0, $timeout, $class);
        }
    }

    public function test_worker_timeout_sits_between_job_timeout_and_retry_after(): void
    {
        $maxJob = QueuedJobTimeouts::maxTimeoutSeconds();
        $worker = (int) config('queue.worker_timeout');

        $this->assertGreaterThanOrEqual($maxJob, $worker);

        foreach (QueuedJobTimeouts::persistentConnectionNames() as $name) {
            $retryAfter = (int) config("queue.connections.{$name}.retry_after");
            $this->assertGreaterThan($worker, $retryAfter, $name);
        }
    }

    /**
     * @return list<string>
     */
    private function anthropicOperations(): array
    {
        return [
            AnalysisAdviceSchema::NAME,
            GenerateCvSchema::NAME,
            EnhanceJobDescriptionSchema::NAME,
            EnhanceCvFieldSchema::NAME,
            ClassifyDocumentSchema::NAME,
            JobMatchAdviceSchema::NAME,
        ];
    }

    public function test_claude_worst_case_job_attempt_fits_inside_job_timeout(): void
    {
        config(['services.cv_ai.provider' => 'claude']);

        $this->assertLessThan(AiTimeouts::JOB_SECONDS, AiTimeouts::worstCaseJobAttemptSeconds());
    }

    public function test_every_anthropic_operation_can_decode_its_full_output_inside_one_attempt(): void
    {
        // Planning rate for Haiku decoding; below it the attempt is cut off
        // before a max-length answer can finish.
        $minimumTokensPerSecond = 120;

        foreach ($this->anthropicOperations() as $operation) {
            $needed = (int) ceil(OperationSchemas::maxTokens($operation) / $minimumTokensPerSecond);

            $this->assertGreaterThanOrEqual(
                $needed,
                AiTimeouts::anthropicAttemptSeconds($operation),
                $operation,
            );
        }
    }

    public function test_every_anthropic_budget_fits_the_job_and_long_calls_can_retry_one_full_timeout(): void
    {
        foreach ($this->anthropicOperations() as $operation) {
            $attempt = AiTimeouts::anthropicAttemptSeconds($operation);
            $budget = AiTimeouts::anthropicBudgetSeconds($operation);

            if (AiTimeouts::isLongAnthropicOperation($operation)) {
                $this->assertGreaterThanOrEqual($attempt, $budget - $attempt, $operation);
            }
            $this->assertLessThan(
                AiTimeouts::JOB_SECONDS,
                AiTimeouts::layerWorstCaseSeconds($budget, $attempt),
                $operation,
            );
        }
    }

    public function test_short_anthropic_operations_keep_the_request_path_budget(): void
    {
        foreach ($this->anthropicOperations() as $operation) {
            if (AiTimeouts::isLongAnthropicOperation($operation)) {
                continue;
            }

            $this->assertSame(AiTimeouts::HTTP_BUDGET_SECONDS, AiTimeouts::anthropicBudgetSeconds($operation), $operation);
        }
    }
}
