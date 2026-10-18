<?php

namespace App\Models;

use App\Cv\CvDocument;
use App\Enums\AiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'keywords_found' => 'array',
            'keywords_missing' => 'array',
            'strengths' => 'array',
            'weaknesses' => 'array',
            'ai_feedback' => 'array',
            'applied_suggestion_ids' => 'array',
            'cv_snapshot' => 'array',
            'ai_status' => AiStatus::class,
        ];
    }

    public static function snapshotHash(CvDocument $document): string
    {
        return hash('sha256', json_encode($document->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** Give legacy entries stable advice targets without mutating the saved source CV. */
    public static function adviceSnapshot(CvDocument $document): array
    {
        $snapshot = $document->toArray();
        foreach ($snapshot['experience'] ?? [] as $index => $entry) {
            if (! isset($entry['id']) || $entry['id'] === '') {
                $snapshot['experience'][$index]['id'] = 'job-match-'.substr(hash('sha256', json_encode([$index, $entry], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 32);
            }
        }

        return $snapshot;
    }

    /** All authored text, including alternate translations and custom sections. */
    public static function snapshotText(CvDocument $document): string
    {
        $parts = [];
        $snapshot = $document->toArray();
        array_walk_recursive($snapshot, static function (mixed $value, string|int $key) use (&$parts): void {
            if (is_string($value) && $value !== '' && ! in_array($key, ['id', 'key', 'export_language'], true)) {
                $parts[] = $value;
            }
        });

        return implode("\n", $parts);
    }

    public static function scoreAttributes(array $score): array
    {
        return [
            'score_total' => $score['total'],
            ...array_intersect_key($score, array_flip([
                'grade', 'job_match', 'category', 'criteria', 'keywords_found', 'keywords_missing', 'strengths', 'weaknesses',
            ])),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generatedCv(): BelongsTo
    {
        return $this->belongsTo(GeneratedCv::class);
    }

    public function appliedGeneratedCv(): BelongsTo
    {
        return $this->belongsTo(GeneratedCv::class, 'applied_generated_cv_id');
    }
}
