FOLLOW-UP on your PDF pre-render work (same task, docs/agents/tasks/cv-speed-codex.md). Fix these review
findings. Same rules: touch only your files plus the ones named here, do not commit.

1. HIGH (privacy) - stored PDFs survive deletion. A stored PDF holds the user's name, phone, email and CV.
   - MobileAuthController::deleteAccount (app/Http/Controllers/MobileAuthController.php:328) deletes CVs with
     a mass query `$user->generatedCvs()->delete()`, so no model event fires and
     storage/app/private/cv-pdfs/{id}/ stays on disk after the account is gone.
   - GeneratedCvController::destroyApi (line ~121) deletes one CV; GeneratedCvObserver has no `deleted` handler.
   Fix: one method on CvTemplateRenderer (or a small CvPdfStore class) that deletes the whole cv-pdfs/{id}
   directory; call it from a GeneratedCvObserver `deleted` handler AND from deleteAccount for every CV id
   before the mass delete. Any other mass delete of generated_cvs (grep for it) gets the same call.
   Test (assert on the artefact, AGENTS.md rule 6): after deleting a CV, and after deleting an account with
   several CVs in both languages, the cv_pdfs disk has no file under those ids; other users' files remain.

2. MEDIUM (performance) - GeneratedCvObserver::templateSaved runs inside the admin's save request, loops
   over EVERY completed CV in the database, calls resolve() per CV and can queue a render for all of them.
   The fingerprint already includes the template, so the next download renders on its own. Remove the
   template fan-out (CvTemplate::saved / ::deleted hooks in AppServiceProvider and templateSaved).

3. MEDIUM (performance) - userSaved fires on every User save while avatar_path is set, and for every
   completed CV it computes a fingerprint (reads and hashes the photo). Only dispatch when
   `$user->wasChanged('avatar_path')`. Check that uploadAvatar (MobileAuthController.php:286) always stores
   a new path; if it can reuse the same path, dispatch from uploadAvatar explicitly instead.

4. LOW - config/cv_pdf.php: 'queue' => env('CV_PDF_QUEUE', 'default').

Update tests for 2 and 3 (no fan-out on template save; no dispatch on an unrelated user save; dispatch on
a new avatar). Run only: php artisan test tests/Feature/CvPdfArtifactTest.php tests/Feature/ProfileAvatarTest.php
tests/Feature/CvTemplateFeatureTest.php plus the account-deletion test file (grep deleteAccount in tests).

5. Full suite: 5 tests now fail because they call Queue::assertNothingPushed() and see RenderCvPdfJob:
   - AsyncCvAiTest::test_headerless_generated_cv_client_keeps_synchronous_behavior
   - JobMatchApplyTest::test_selected_edits_preserve_untouched_document_data_and_sync_all_persisted_formats
   - JobMatchControllerTest::test_foreign_records_return_404_at_every_endpoint
   - JobMatchControllerTest::test_snapshot_limit_cannot_be_bypassed_using_secondary_translations_or_custom_sections
   - JobMatchControllerTest::test_retry_advice_is_premium_and_owner_only_and_does_not_spend_quota
   For each, first find WHO pushed the render job. If it comes from test setup (factory creating a completed
   CV), keep the test's meaning: assert the AI jobs it cares about were not pushed (assertNotPushed for each
   AI job class) instead of nothing at all. If a request that should be rejected (foreign user, 404, quota
   denied) caused the push, that is a bug in the observer: fix the code, not the test. Do not weaken any
   other assertion. (LandingLeadTest::test_landing_page_can_be_viewed also fails; it is unrelated, leave it.)

FINAL ANSWER: files changed, test results, LEARNINGS: lines.
