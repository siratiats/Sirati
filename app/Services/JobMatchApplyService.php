<?php

namespace App\Services;

use App\Cv\CvDocument;
use App\Cv\LocalizedText;
use App\Enums\AiStatus;
use App\Models\GeneratedCv;
use App\Models\JobMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobMatchApplyService
{
    public function __construct(private readonly AtsScoringService $scorer) {}

    public function apply(JobMatch $match, array $keys, array $confirmedFactKeys = []): GeneratedCv
    {
        return DB::transaction(function () use ($match, $keys, $confirmedFactKeys): GeneratedCv {
            $match = JobMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $source = GeneratedCv::where('user_id', $match->user_id)->whereKey($match->generated_cv_id)
                ->lockForUpdate()->first();
            abort_unless($source !== null, 409, 'The source CV no longer exists.');
            abort_unless(hash_equals($match->cv_snapshot_hash, JobMatch::snapshotHash($source->cvDocument())), 409, 'The source CV has changed. Create a new Job Match.');
            abort_unless($match->ai_status === AiStatus::Completed, 409, 'Advice is not ready.');
            $this->validateKeys($keys, 'suggestion_keys', required: true);
            $this->validateKeys($confirmedFactKeys, 'confirmed_fact_keys');
            if (array_diff($confirmedFactKeys, $keys) !== []) {
                $this->invalid('Confirmed facts must be selected suggestions.', 'confirmed_fact_keys');
            }
            $suggestions = [];
            foreach ($match->ai_feedback['suggestions'] ?? [] as $suggestion) {
                $key = $suggestion['key'] ?? null;
                if (! is_string($key) || isset($suggestions[$key])) {
                    $this->invalid('Advice contains invalid or duplicate suggestion keys.');
                }
                $suggestions[$key] = $suggestion;
            }
            $previous = $match->applied_suggestion_ids ?? [];
            foreach ($confirmedFactKeys as $key) {
                if (($suggestions[$key]['needs_user_fact'] ?? null) !== true) {
                    $this->invalid('Only suggestions requiring a user fact can be confirmed.', 'confirmed_fact_keys');
                }
            }
            // Saved attestations remain valid for incremental apply and request replay.
            $confirmed = array_values(array_unique([...($match->confirmed_fact_keys ?? []), ...$confirmedFactKeys]));
            $all = array_values(array_unique([...$previous, ...$keys]));
            $baseline = CvDocument::fromArray($match->cv_snapshot)->toArray();
            $document = $baseline;
            $targets = [];
            foreach ($all as $key) {
                if (! is_string($key) || ! isset($suggestions[$key])) {
                    $this->invalid('Unknown suggestion.');
                }
                $suggestion = $suggestions[$key];
                $needsFact = $suggestion['needs_user_fact'] ?? null;
                if (! is_bool($needsFact) || ($needsFact && ! in_array($key, $confirmed, true))) {
                    $this->invalid('This suggestion requires a user supplied fact.');
                }
                $kind = $suggestion['kind'] ?? null;
                $after = $suggestion['after'] ?? null;
                if (! in_array($kind, ['replace', 'append_skill', 'append_bullet'], true)
                    || ! is_string($after) || trim($after) === '' || preg_match('/\R/u', $after)) {
                    $this->invalid('Invalid suggestion text.');
                }
                $before = $suggestion['before'] ?? null;
                if ($kind !== 'append_skill' && ! is_string($before)) {
                    $this->invalid('Invalid suggestion text.');
                }
                $target = $suggestion['target'] ?? [];
                $section = $target['section'] ?? null;
                $field = $target['field'] ?? null;
                $entryId = $target['entry_id'] ?? null;
                $bulletIndex = $target['bullet_index'] ?? null;
                $lengthCap = $section === 'skills' ? 60 : ($section === 'summary' ? 2000 : 500);
                if (mb_strlen($after) > $lengthCap) {
                    $this->invalid('Invalid suggestion text.');
                }
                $language = $match->language;
                if ($kind === 'replace' && $section === 'summary' && in_array($field, ['summary', 'text'], true)
                    && in_array($entryId, [null, 'summary'], true) && $bulletIndex === null) {
                    $this->expectBefore($baseline['summary'], $language, $before);
                    $this->reserve($targets, 'summary');
                    $document['summary'][$language] = $after;
                } elseif ($kind === 'append_skill' && $section === 'skills' && in_array($field, ['name', 'append'], true)
                    && $entryId === null && $bulletIndex === null) {
                    abort_unless($before === null || $before === '', 409, 'Skill append must have an empty before value.');
                    if ($after !== trim($after)) {
                        $this->invalid('Invalid suggestion text.');
                    }
                    // Multiple distinct append suggestions are safe; never replace existing skill metadata.
                    $this->reserve($targets, 'skill:'.mb_strtolower(trim($after)));
                    $existing = array_map(fn (array $skill): string => mb_strtolower(LocalizedText::fromArray($skill['name'])->resolve($language)), $document['skills'] ?? []);
                    if (! in_array(mb_strtolower(trim($after)), $existing, true)) {
                        $document['skills'][] = ['id' => (string) Str::uuid(), 'name' => [$language => trim($after)]];
                    }
                } elseif ($kind === 'replace' && $section === 'experience' && in_array($field, ['bullet', 'bullets'], true) && is_string($entryId) && $entryId !== '') {
                    $index = $bulletIndex;
                    if (! is_int($index) || $index < 0) {
                        $this->invalid('Invalid bullet index.');
                    }
                    $entries = array_keys(array_filter($baseline['experience'] ?? [], fn (array $entry): bool => ($entry['id'] ?? null) === $entryId));
                    abort_unless(count($entries) === 1, 409, 'Experience entry no longer exists or is ambiguous.');
                    $entryIndex = $entries[0];
                    $before = $baseline['experience'][$entryIndex]['bullets'][$index] ?? null;
                    abort_unless(is_array($before), 409, 'Experience bullet no longer exists.');
                    $this->expectBefore($before, $language, $suggestion['before']);
                    $this->reserve($targets, 'experience:'.$entryId.':'.$index);
                    $document['experience'][$entryIndex]['bullets'][$index][$language] = $after;
                } else {
                    $this->invalid('Unsupported suggestion target.');
                }
            }
            if (array_diff($keys, $previous) === [] && $match->applied_generated_cv_id !== null) {
                return GeneratedCv::where('user_id', $match->user_id)->findOrFail($match->applied_generated_cv_id);
            }
            $updated = CvDocument::fromArray($document)->withExportLanguage($match->language);
            if (mb_strlen(JobMatch::snapshotText($updated)) > 7000) {
                $this->invalid('The updated CV exceeds 7000 characters.');
            }
            $score = $this->scorer->scoreDocument($updated, null, $match->job_description_input);
            $copy = $source->replicate(['idempotency_key']);
            $snapshot = $updated->legacySnapshot();
            $copy->fill([
                ...$snapshot,
                'document' => $updated->toArray(),
                'generated_markdown' => $updated->resolve()->toMarkdown(),
                'form_payload' => [...($source->form_payload ?? []), ...$snapshot, 'document' => $updated->toArray(), 'job_description_input' => $match->job_description_input],
                'job_description_input' => $match->job_description_input,
                'ai_status' => AiStatus::Completed,
                'ai_output' => null,
                'ai_error' => null,
                'score_total' => $score['total'],
                'grade' => $score['grade'],
                'criteria' => $score['criteria'],
            ]);
            $copy->idempotency_key = null;
            $copy->save();
            $match->update([
                ...JobMatch::scoreAttributes($score),
                'applied_suggestion_ids' => $all,
                'confirmed_fact_keys' => $confirmed,
                'applied_generated_cv_id' => $copy->id,
                // Identifiers and attestations only; never persist CV text in the audit.
                'suggestion_application_audit' => [...($match->suggestion_application_audit ?? []), [
                    'applied_suggestion_ids' => $all,
                    'confirmed_fact_keys' => $confirmed,
                    'applied_generated_cv_id' => $copy->id,
                    'applied_at' => now()->toISOString(),
                ]],
            ]);

            return $copy;
        });
    }

    private function expectBefore(array $value, string $language, string $expected): void
    {
        abort_unless(LocalizedText::fromArray($value)->resolve($language) === $expected, 409, 'Suggestion no longer matches the CV.');
    }

    private function reserve(array &$targets, string $target): void
    {
        if (isset($targets[$target])) {
            $this->invalid('Selected suggestions edit the same target.');
        }
        $targets[$target] = true;
    }

    private function validateKeys(array $keys, string $field, bool $required = false): void
    {
        if (! array_is_list($keys) || ($required && $keys === []) || count($keys) > 50) {
            $this->invalid('Use a list of at most 50 suggestion keys.', $field);
        }
        foreach ($keys as $key) {
            if (! is_string($key) || trim($key) === '' || mb_strlen($key) > 128) {
                $this->invalid('Invalid suggestion key.', $field);
            }
        }
        if (count(array_unique($keys)) !== count($keys)) {
            $this->invalid('Suggestion keys must be unique.', $field);
        }
    }

    private function invalid(string $message, string $field = 'suggestion_keys'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
