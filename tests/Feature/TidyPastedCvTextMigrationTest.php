<?php

namespace Tests\Feature;

use App\Enums\AiStatus;
use App\Jobs\RenderCvPdfJob;
use App\Models\GeneratedCv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TidyPastedCvTextMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_saved_before_the_rules_are_cleaned_once_without_queueing_renders(): void
    {
        Queue::fake();
        $cv = GeneratedCv::create([
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Maya Noor',
            'target_job_title' => 'Marketing',
            'language' => 'en',
            'summary_input' => 'Summary.',
            'skills_input' => 'Ads',
            'experience_input' => 'Marketing | Example Co | 2020 - 2024',
            'education_input' => 'BSc Marketing',
            'form_payload' => [],
            'generated_markdown' => '',
            'ai_status' => AiStatus::Completed,
        ]);
        $lines = ['Raised traffic and conversion across every product category on the', 'website and the app by 30 percent.', 'Ran weekly reports.'];
        $document = $cv->cvDocument()->toArray();
        $document['experience'] = [[
            'bullets' => array_map(fn (string $line): array => ['ar' => '', 'en' => $line], $lines),
            'narrative' => ['ar' => '', 'en' => implode("\n", $lines)],
        ]];
        // Bypass the model: this is how rows saved before the rules look.
        DB::table('generated_cvs')->where('id', $cv->id)->update([
            'document' => json_encode($document),
            'summary_input' => 'Oﬃce and traﬃc oﬀers.',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
        Queue::fake();

        $migration = require database_path('migrations/2026_10_26_000001_tidy_pasted_cv_text.php');
        $migration->up();
        $fresh = $cv->fresh();

        $this->assertSame('Office and traffic offers.', $fresh->summary_input);
        $this->assertSame(
            ['Raised traffic and conversion across every product category on the website and the app by 30 percent.', 'Ran weekly reports.'],
            array_column($fresh->document['experience'][0]['bullets'], 'en'),
        );
        $this->assertSame('', $fresh->document['experience'][0]['narrative']['en']);
        $this->assertSame('2026-01-01 00:00:00', $fresh->getRawOriginal('updated_at'));
        Queue::assertNotPushed(RenderCvPdfJob::class);

        $before = $fresh->getAttributes();
        $migration->up();
        $this->assertSame($before, $cv->fresh()->getAttributes(), 'A second run changes nothing.');
    }
}
