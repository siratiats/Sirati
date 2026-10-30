# Server text the app can show (Abobker85/Sirati#12)

Every message below comes from the label catalog (`server*` keys in `resources/i18n/app_*.arb`, copied from the Flutter ARB files). It is served in the app language and is editable on the admin Labels page (group `server`). An edit takes effect once it is published.

**How the language is picked.** The app sends `X-App-Language` on every call (`ApiClient`). `App\Http\Middleware\SetRequestLanguage` uses that header first, then `?lang=`, then the default language. A language that is not enabled falls back through its fallback chain (`ServerText::chain`). `?lang=` still selects content rows (job posts, education) in `ar` or `en`. `Accept-Language` is ignored because HTTP stacks fill it from the device, not from the language chosen in the app.

**Resolution.** `App\Services\I18n\ServerText` looks up each language in the chain: first its published value, then its bundled ARB value. Laravel validation lines come from the same catalog through `CatalogTranslationLoader`.

| Kind | Where it is produced | Keys | Language | Editable |
|---|---|---|---|---|
| Content-API titles and subtitles (dashboard actions, My CVs, CV designs, education tabs, job feed) | `MobileContentController` | `serverDashboard*`, `serverMyCvs*`, `serverCvTemplates*`, `serverEducation*`, `serverJobs*` | app | yes |
| Job source labels and badges, salary text, "Apply by" | `JobProfileMatcherService`, `MobileContentController::jobNewsPayload` | `serverJobsSource*`, `serverJobsSalary*`, `serverJobsCurrencySar`, `serverJobsApplyBy` | app | yes |
| Auth and account messages (login, verify, reset, password, avatar, delete, social sign-in) | `MobileAuthController`, `EmailVerificationService`, `PasswordResetService`, `SocialAuthException`, `EnsureEmailIsVerified` | `serverAuth*` | app | yes |
| CV messages (deleted, template unavailable/premium, export blocked, template hides sections) | `GeneratedCvController`, `CvTemplateRenderer`, `CvExportGuard`, `TemplateSwitchResult` | `serverCv*` | app (export guard and template switch: both `_ar`/`_en`, or the CV language) | yes |
| AI field-enhancement notes | `GeneratedCvController::enhanceField` | `serverCvEnhanceUnavailable`, `serverCvEnhanceFailed` | **CV language** (shown with the CV draft) | yes |
| Analysis upload errors and the locked-analysis card | `CvAnalysisController`, `CvTextExtractor`, `CvAnalysisResource` | `serverAnalysis*`, `serverCvFileTypeUnsupported`, `serverCvTextTooShort`, `serverCvPdfUnreadable` | app (locked card: `_ar`/`_en`) | yes |
| Paywall (402), plan names and badges, free-plan list, download lock | `SubscriptionRequiredException`, `SubscriptionGate`, `config/monetization.php` (`name_key`/`badge_key`), `SubscriptionPlanController`, `GeneratedCvResource` | `serverPaywall*`, `serverPlan*`, `serverJobMatchDailyLimit`, `serverCvDownloadLocked` | `message`/`name`/`title`: app; `_ar`/`_en` pairs for older builds | yes |
| Validation errors (422) | Laravel validator + `CatalogTranslationLoader`; summary in `ApiErrorText` | `serverValidation*`, `serverAttribute*` | app | yes |
| Framework errors (401, 403, 404, 405, 429, 5xx, 503 fail-closed) | `ApiErrorText` (`bootstrap/app.php`), `FailClosed` | `serverError*` | app | yes |
| Notification acknowledgements (all read, preferences, engagement, device token) | `MobileContentController`, `NotificationPreferenceController`, `NotificationEngagementController`, `FcmTokenController` | `serverNotifications*` | app | yes |
| Templated notifications: in-app list | `NotificationTemplateService`, stored as `title_key`/`body_key`/`text_params` on `mobile_notifications` | `serverNotify*` | **reader's app language** at read time | yes |
| Templated notifications: push (FCM) | `SendPlannedNotificationJob`, `SendPushNotificationJob` (CV ready/failed, new job) | `serverNotify*` | user's notification language (`notification_preferences.language`, sent by the app on token registration) | yes |

## Stored text (migration, not only a catalog change)

- `2026_10_30_100001_import_server_labels` adds the `server*` keys to the catalog on deploy. It never overwrites admin-published values.
- `2026_10_30_100002_add_text_keys_to_mobile_notifications` adds `title_key`, `body_key` and `text_params`. It backfills rows stored before this change: text that a template produced (in Arabic or English) gets its keys and values back, so old notifications also follow the reader's language.

## Shown as written (deliberately not catalog labels)

- Admin broadcasts and test pushes (`SendBulkNotificationJob`, `notifications:send-test`): an admin types the text. These rows have no keys, and the list shows them as written.
- Data rows: job posts, education content, CV templates and job-title names. These are content in their own language, edited on their own admin pages.
- CV content that the server writes into a CV (fallback summaries, "Present", section headings, watermark): it follows the CV's language. This is the open item listed in PROJECT_MEMORY "Not yet catalogued".

## Not shown by the app

- Job Match validation and conflict messages (`JobMatchController`, `JobMatchApplyService`): the app maps the status to its own labels (`JobMatchFailure`).
- Webhook, admin panel and `/api/i18n` messages: these are not part of the mobile UI.
- Framework fields with no `serverAttribute*` key (for example `device_id`, `idempotency_key`): their errors only appear when the client itself is wrong.
