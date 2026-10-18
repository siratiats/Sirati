<?php

namespace Database\Factories;

use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Models\JobMatch;
use App\Models\User;
use App\Services\AtsScoringService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobMatch> */
class JobMatchFactory extends Factory
{
    public function definition(): array
    {
        $document = new CvDocument(
            exportLanguage: 'en',
            summary: LocalizedText::make(en: fake()->paragraph()),
        );
        $title = fake()->jobTitle();
        $description = fake()->paragraph();
        $snapshotHash = JobMatch::snapshotHash($document);

        return [
            'user_id' => User::factory(),
            'generated_cv_id' => null,
            'idempotency_key' => fake()->uuid(),
            'request_fingerprint' => hash('sha256', json_encode([
                null, $description, 'en', $title, $snapshotHash,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'target_job_title' => $title,
            'job_description_input' => $description,
            'language' => 'en',
            'cv_snapshot' => JobMatch::adviceSnapshot($document),
            'cv_snapshot_hash' => $snapshotHash,
            'ai_status' => 'completed',
            ...JobMatch::scoreAttributes(app(AtsScoringService::class)->scoreDocument($document, null, $description)),
        ];
    }
}
