<?php

namespace App\Http\Controllers;

use App\Http\Resources\GeneratedCvResource;
use App\Http\Resources\JobMatchResource;
use App\Jobs\GenerateJobMatchAdviceJob;
use App\Models\GeneratedCv;
use App\Models\JobMatch;
use App\Models\User;
use App\Services\AtsScoringService;
use App\Services\JobMatchApplyService;
use App\Services\Subscriptions\SubscriptionGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class JobMatchController extends Controller
{
    public function store(Request $request, SubscriptionGate $gate, AtsScoringService $scorer)
    {
        $key = $request->header('Idempotency-Key');
        $gate->assertCanAccessJobMatch($request->user());
        $input = $request->validate([
            'generated_cv_id' => ['required', 'integer', 'min:1'],
            'job_description_input' => ['required', 'string', 'max:4000'],
            'target_job_title' => ['sometimes', 'string', 'max:160'],
            'language' => ['sometimes', Rule::in(['ar', 'en'])],
        ]);
        if ($key !== null && (trim($key) === '' || strlen($key) > 128)) {
            throw ValidationException::withMessages(['idempotency_key' => 'Use a non-empty key of at most 128 bytes.']);
        }

        [$match, $created] = DB::transaction(function () use ($request, $gate, $scorer, $input, $key): array {
            // Every create for this user takes the same lock, including requests with different keys.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $cv = GeneratedCv::where('user_id', $request->user()->id)
                ->lockForUpdate()->findOrFail($input['generated_cv_id']);
            $document = $cv->cvDocument();
            if (mb_strlen(JobMatch::snapshotText($document)) > 7000) {
                throw ValidationException::withMessages(['generated_cv_id' => 'The CV must not exceed 7000 characters.']);
            }
            $language = $input['language'] ?? $document->exportLanguage;
            $title = $input['target_job_title'] ?? $cv->target_job_title;
            $fingerprint = hash('sha256', json_encode([
                (int) $cv->id, $input['job_description_input'], $language, $title, JobMatch::snapshotHash($document),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if ($key !== null) {
                $existing = JobMatch::where('user_id', $request->user()->id)->where('idempotency_key', $key)->first();
                if ($existing !== null) {
                    abort_unless(hash_equals($existing->request_fingerprint, $fingerprint), 409, 'Idempotency key belongs to a different request.');

                    return [$existing, false];
                }
            }
            // New attempts must pass the quota again under the per-user lock.
            $gate->assertCanJobMatch($request->user());

            return [JobMatch::create([
                'user_id' => $request->user()->id,
                'generated_cv_id' => $cv->id,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'target_job_title' => $title,
                'job_description_input' => $input['job_description_input'],
                'language' => $language,
                'cv_snapshot' => JobMatch::adviceSnapshot($document),
                'cv_snapshot_hash' => JobMatch::snapshotHash($document),
                ...JobMatch::scoreAttributes($scorer->scoreDocument($document, null, $input['job_description_input'])),
            ]), true];
        });

        if ($created) {
            try {
                GenerateJobMatchAdviceJob::dispatch($match->id)->afterCommit();
            } catch (Throwable $exception) {
                $match->update(['ai_status' => 'failed', 'ai_error' => 'Job Match advice is unavailable.']);
                Log::warning('Job Match advice dispatch failed.', ['job_match_id' => $match->id, 'exception' => $exception::class]);
            }
        }

        return (new JobMatchResource($match->refresh()))->response()->setStatusCode($created ? 201 : 200);
    }

    public function show(Request $request, int $id, SubscriptionGate $gate): JobMatchResource
    {
        $match = JobMatch::where('user_id', $request->user()->id)->findOrFail($id);
        $gate->assertCanAccessJobMatch($request->user());

        return new JobMatchResource($match);
    }

    public function applySuggestions(Request $request, int $id, SubscriptionGate $gate, JobMatchApplyService $service): array
    {
        $match = JobMatch::where('user_id', $request->user()->id)->findOrFail($id);
        $gate->assertCanAccessJobMatch($request->user());
        $input = $request->validate([
            'suggestion_keys' => ['required', 'array', 'min:1', 'max:50'],
            'suggestion_keys.*' => ['required', 'string', 'max:128', 'distinct'],
        ]);
        $cv = $service->apply($match, $input['suggestion_keys']);

        return ['data' => [
            'generated_cv' => new GeneratedCvResource($cv),
            'job_match' => new JobMatchResource($match->refresh()),
        ]];
    }
}
