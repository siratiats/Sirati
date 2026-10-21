# CV builder

Signed-in users create an ATS-focused CV with the four-step AI generator, edit a generated CV, and manage server-saved CVs in My CVs. A separate structured document builder provides section editing and a live preview; its local storage is independent of the generated-CV list.

## User flow

- **Create CV:** Personal information (name, email, phone, target role, optional job description, CV output language) → skills and optional summary → experience → education and optional certifications. Next validates the current step; the last button generates. Job title and description can be prefilled from a job post.
- **Writing aids:** The job description and skills, summary, experience, education, and certifications can be enhanced by AI when enough draft text exists. Results replace the field and expose review details; a snackbar offers Undo for field enhancement. Skills can be added or removed as chips, with role-based suggestions. The experience step opens an XYZ achievement sheet with presets and a live sentence; insertion avoids duplicate bullets.
- **Drafts and generation:** In create mode, edits autosave after two seconds. A returning user can restore the saved step or start fresh; leaving a dirty form offers save, discard, or cancel. Generation shows queued/processing progress and can be cancelled. A timeout leaves the user in the wizard with a warning; an AI failure shows a retry message. A completed create opens the generated CV; editing returns the updated CV to its caller.
- **Generated CV:** Shows ATS status and score, parsed CV content, Edit, plain-text Share, and Download PDF. Empty content disables Share. Download is blocked while AI is pending or failed, may offer a template picker, and opens a PDF URL externally; unavailable links and launch failures show a message. `/cv/<id>` first shows loading, then the CV or a retryable error.
- **My CVs tab:** Loads saved rows with edit, download, delete, and create actions. A blank list offers Create New CV. Loading uses a skeleton; errors offer Retry. A cached offline list shows an offline banner. Delete asks for confirmation, removes the row after the API succeeds, then refreshes; download may offer a template picker and falls back to a stored URL if fetching a fresh one fails.
- **Structured builder:** `/builder` creates a local document; `/builder/<id>` loads one. Personal, experience/education, skills/languages, and section-order tabs edit a structured bilingual document. Save Now, save status, and live preview are available; wide layouts show the preview beside the editor. Loading and retryable load-error states are present. A newer crash draft can be restored or discarded; edits save on a debounce, app backgrounding, and exit.

## Entry points

- `AppRoutes.createCv` (`/create-cv`) opens the generator; dashboard, history, education, My CVs, and job actions also push it. `AppRoutes.myCvs` (`/mycvs`) selects the My CVs home tab. `AppRoutes.cv(id)` (`/cv/<id>`) loads a generated CV; history cards also open one.
- `AppRoutes.cvBuilder` (`/builder`) and `AppRoutes.builder(id)` (`/builder/<id>`) open the structured builder. These named routes accept deep links through the app router, including `sirati://app/...`.
- Notification screen targets `create-cv`/`cv-generator` open the generator; `my-cvs`/`mycvs` select the My CVs tab. There is no notification target for the structured builder or a specific generated CV.

## Code map

| Path | Responsibility |
| --- | --- |
| `lib/features/cv_builder/presentation/cv_generator_screen.dart` | Wizard navigation, validation, enhancement UI, drafts, submit outcomes. |
| `lib/features/cv_builder/presentation/cv_generator/steps/` | Four wizard step widgets; `lib/features/cv_builder/presentation/widgets/achievement_builder_sheet.dart` and `lib/features/cv_builder/presentation/widgets/skill_chips_selector.dart` supply writing aids. |
| `lib/features/cv_builder/presentation/generated_cv_screen.dart` | Generated CV, edit/share/PDF actions; `lib/features/cv_builder/presentation/generated_cv_loader_screen.dart` handles ID links. |
| `lib/features/cv_builder/presentation/my_cvs_screen.dart` | My CVs tab and actions; `lib/features/cv_builder/presentation/my_cvs/cv_template_picker.dart` selects server templates. |
| `lib/features/cv_builder/presentation/cv_builder_screen.dart` | Structured builder tabs, save, recovery, preview; `lib/features/cv_builder/presentation/cv_live_preview_pane.dart` renders the local document. |
| `lib/features/cv_builder/application/cv_generation_controller.dart`, `lib/features/cv_builder/application/cv_enhancement_controller.dart`, `lib/features/cv_builder/application/cv_draft_store.dart` | Async generation, enhancement, and secure wizard draft. |
| `lib/features/cv_builder/application/my_cvs_controller.dart`, `lib/features/cv_builder/domain/cv_list.dart`, `lib/features/cv_builder/domain/cv_form_data.dart` | Server list state/URL resolution, list parsing, and form payload. |
| `lib/features/cv_builder/controllers/cv_builder_controller.dart`, `lib/features/cv_builder/data/cv_repository.dart`, `lib/features/cv_builder/data/cv_schema_migrator.dart` | Local document autosave/recovery, storage engines, and schema migration. |
| `lib/shared/services/cv_api_service.dart`, `lib/shared/services/mobile_content_service.dart` | Generated-CV API and cached mobile content. |

## State and data

- `cvGenerationControllerProvider` reports busy state and returns sealed `CvGenerated`, `CvGenerationCancelled`, or `CvGenerationFailed`. Create retries reuse an idempotency key until success; create/update uses asynchronous AI polling. `cvFieldEnhancerProvider` and `jobDescriptionEnhancerProvider` expose active enhancement state and sealed enhanced/stale/failed outcomes; newer requests supersede older ones.
- `myCvsProvider(english)` is an auto-disposed async family. The first load may use the 60-second mobile-content memory cache; later loads and pull-to-refresh force a fetch. Network/timeout/server errors can fall back to a language-specific SharedPreferences disk cache (`sirati_cache_cvs_ar` or `sirati_cache_cvs_en`) and mark the list offline. Successful CV changes invalidate My CVs/dashboard memory entries. Content caches are cleared on sign-out/session expiry.
- The create wizard stores its nonempty form, step, timestamp, and schema version in secure storage key `sirati_cv_draft`. It clears on Start Fresh, discard, successful create, schema mismatch, and account-data wipe on sign-out/session expiry. Edit mode does not use that draft.
- The structured builder uses `cvRepositoryProvider` and `CvBuilderController` (`ChangeNotifier`), not the My CVs provider. The default `LocalCvRepository` uses an in-memory engine; optional file and SharedPreferences engines exist in the repository. Dirty documents are backed up under SharedPreferences prefix `sirati_cv_draft_<id>` and cleared after a successful current-snapshot save or explicit discard. The SharedPreferences document engine uses `sirati_cv_doc_<id>` and index `sirati_cv_ids`; these keys are wiped with account data. `CvManageController` supports local create/duplicate/rename/delete/15-second undo but is not used by the My CVs screen.

## API

| Method and path | Used for |
| --- | --- |
| `POST /generated-cvs`, `PUT /generated-cvs/<id>` | Create/update from `CvFormData`: name, contact fields, target role, job description, language, summary, skills, experience, education, certifications. Create sends an idempotency key; both request async AI. Response `data` includes ID, AI status, markdown, score, and PDF links. |
| `GET /generated-cvs/<id>` | Poll AI status and load a CV for edit/deep link or a fresh PDF link. Polling waits 2 seconds initially, then 5 seconds, up to 210 seconds. |
| `POST /generated-cvs/enhance-field`, `POST /generated-cvs/enhance-job-description` | Send field/draft/job title/language or target title/description/language. Read `enhanced_text` plus review lists or `enhanced_description`; empty output is treated as failure. |
| `GET /mobile/my-cvs?lang=<ar\|en>`, `DELETE /generated-cvs/<id>` | List localized rows (`items`, title, draft/download flags, links) and delete a generated CV. |
| `GET /mobile/cv-templates?lang=<ar\|en>`, `GET /mobile/job-titles` | Template picker (`data.items`) and optional job title suggestions. Failed title fetch leaves free-text input usable; failed template fetch uses the default design. |

API failures surface as localized snackbars or retryable list/load states. PDF links come from CV response fields (`pdf_url`, `template_pdf_url`) with an optional `template` query parameter; the structured local builder makes no API request.

## Localization and RTL

- UI text follows `AppLocale` or generated `AppLocalizations`; Arabic is the default. The wizard's CV output language is independent of the app UI locale. Generated content uses its declared `ar`/`en` direction, or detects direction from markdown; contact text stays LTR.
- Wizard navigation uses `DirectionalIcon`; achievement text protects Latin tokens in Arabic. Local document fields store Arabic/English variants. Skill and language-level values are stable Arabic storage keys with localized display labels and English export mappings.

## Tests

- `test/features/cv_builder/cv_generator_controllers_test.dart`, `test/features/cv_builder/cv_form_data_test.dart`, `test/features/cv_builder/cv_draft_store_test.dart`, `test/ai_cv_field_test.dart`: generation/enhancement outcomes, payload and draft round-trips, secure-store failure tolerance, enhancement UI.
- `test/features/cv_builder/achievement_builder_sheet_test.dart`, `test/features/cv_builder/skill_chips_selector_test.dart`: XYZ bullets, duplicate prevention, presets, chip synchronization and suggestions.
- `test/features/cv_builder/generated_cv_test.dart`, `test/features/cv_builder/my_cvs_controller_test.dart`, `test/features/cv_builder/my_cvs_domain_test.dart`, `test/features/cv_builder/my_cvs_card_contrast_test.dart`: content/share conversion, readiness, PDF URL fallback, list state/parsing, card contrast.
- `test/features/cv_builder/cv_builder_controller_test.dart`, `test/features/cv_builder/cv_builder_screen_test.dart`, `test/features/cv_builder/cv_live_preview_pane_test.dart`, `test/cv_builder_test.dart`, `test/cv_document_test.dart`, `test/cv_persistence_and_management_test.dart`: local saving/recovery, screen behavior, preview, document invariants, migration, storage, and management.
- `test/cv_api_service_test.dart` covers create JSON, Laravel errors, and polling.

## Known gaps

- `lib/features/cv_builder/data/cv_repository.dart:266` and `lib/features/cv_builder/data/cv_repository_provider.dart:11`: the default local repository uses memory storage, so structured builder documents do not survive an app restart; the file/SharedPreferences engines are not selected there.
- `lib/features/cv_builder/presentation/cv_builder_screen.dart:107`: an unknown builder ID opens a new empty document with that ID, instead of a not-found state.
- `lib/features/cv_builder/presentation/my_cvs_screen.dart:120`: the list download path opens a URL externally; it does not save PDF bytes into app storage.
