<?php

use App\Models\GeneratedCv;
use Illuminate\Database\Migrations\Migration;

/**
 * One pass of the ingestion rules (GeneratedCv::setAttribute) over CVs saved
 * before them: Latin ligatures, hard-wrapped bullets, narratives that repeat
 * their bullets, pasted degree lists, "Name — Issuer" certificates.
 *
 * Quiet saves without timestamps: the observer would queue a render for every
 * CV. The PDF fingerprint covers the document, so the next download renders.
 */
return new class extends Migration
{
    public function up(): void
    {
        GeneratedCv::query()->chunkById(200, function ($cvs): void {
            foreach ($cvs as $cv) {
                foreach ($cv->getAttributes() as $key => $value) {
                    if (is_string($value) && ! in_array($key, ['document', 'form_payload', 'ai_output', 'criteria'], true)) {
                        $cv->setAttribute($key, $value);
                    }
                }
                foreach (['document', 'form_payload'] as $key) {
                    if (is_array($cv->{$key}) && $cv->{$key} !== []) {
                        $cv->setAttribute($key, $cv->{$key});
                    }
                }

                if ($cv->isDirty()) {
                    $cv->timestamps = false;
                    $cv->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        // Text normalization is not reversible; the original ligatures and
        // wraps carried no information the CV needs.
    }
};
