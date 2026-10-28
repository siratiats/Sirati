FOLLOW-UP 2 (same Codex session). Your deletion and observer fixes look good. One item from the last prompt
was not done: these 5 tests still fail with "The following jobs were pushed unexpectedly: App\Jobs\RenderCvPdfJob":

- tests/Feature/AsyncCvAiTest.php: test_headerless_generated_cv_client_keeps_synchronous_behavior
- tests/Feature/JobMatchApplyTest.php: test_selected_edits_preserve_untouched_document_data_and_sync_all_persisted_formats
- tests/Feature/JobMatchControllerTest.php: test_foreign_records_return_404_at_every_endpoint
- tests/Feature/JobMatchControllerTest.php: test_snapshot_limit_cannot_be_bypassed_using_secondary_translations_or_custom_sections
- tests/Feature/JobMatchControllerTest.php: test_retry_advice_is_premium_and_owner_only_and_does_not_spend_quota

For EACH test, first find who pushed the render job (test setup, or the request under test).
- From setup (a factory or update creating a completed CV): keep the test's meaning. Replace
  Queue::assertNothingPushed() with Queue::assertNotPushed(...) for each AI job class the test is about
  (GenerateCvContentJob, GenerateCvAdviceJob, GenerateJobMatchAdviceJob, as relevant), or clear the fake after
  setup. Do not weaken any other assertion.
- From a request that should change nothing (foreign user, 404, quota denied): that is a bug. Fix the code so
  the rejected request does not save the CV, not the test.
- The headerless synchronous client legitimately completes a CV, so a render job there is expected: assert
  the AI job was not queued AND that exactly one RenderCvPdfJob was pushed.

Say in your answer, per test, which case it was.

Run only: php artisan test tests/Feature/AsyncCvAiTest.php tests/Feature/JobMatchApplyTest.php tests/Feature/JobMatchControllerTest.php tests/Feature/CvPdfArtifactTest.php

FINAL ANSWER: per-test cause, files changed, test results, LEARNINGS: lines.
