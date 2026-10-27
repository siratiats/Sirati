# Admin rebuild + remote labels & languages (2026-10-27)

Goal: admins manage and monitor the app from one dashboard, and every app label and
language is controlled from the admin panel without shipping a new app release.

## 1. Remote labels & languages (core)

### Data (Laravel)
- `app_languages`: code (unique, e.g. `ar`, `en`, `fr`), name, native_name, direction (`ltr|rtl`),
  fallback_code (default `en`), is_enabled, is_default, sort_order, version (int, bumped on publish),
  published_at, timestamps.
- `app_translation_keys` (catalog): key (unique), description, placeholders (json: name -> {type, format}),
  is_icu (plural/select), group (derived from key prefix), timestamps.
- `app_translations`: language_code, key, value (published, nullable), draft_value (nullable),
  updated_by, timestamps; unique(language_code, key).
- `app_translation_revisions`: language_code, version, snapshot (json of published strings), published_by,
  created_at (rollback source).
- Base catalog = the Flutter ARB files copied to `resources/i18n/app_en.arb` + `app_ar.arb`;
  `php artisan i18n:import-arb` inserts missing keys/values, never overwrites admin edits unless `--force`.

### Public API (unauthenticated, throttled, read-only, published values only)
- `GET /api/i18n/languages` ->
  `{"default":"ar","data":[{"code":"ar","name":"Arabic","native_name":"العربية","direction":"rtl","fallback":"en","version":12}]}`
  (enabled languages only, sorted by sort_order).
- `GET /api/i18n/languages/{code}/strings` ->
  `{"code":"ar","direction":"rtl","fallback":"en","version":12,"strings":{"key":"value"}}`
  with `ETag: "ar-12"`, `Cache-Control: public, max-age=300`; `If-None-Match` -> 304;
  404 for unknown or disabled languages. Only non-empty published values are included.

### Safety rules
- Edits are drafts; a language goes live only on **Publish** (version + 1, revision snapshot). Rollback
  republishes an older snapshot as a new version.
- Server validates every value: ICU braces balanced, placeholder names exactly match the catalog's
  placeholders for that key, plural/select keep an `other` branch. Invalid values cannot be saved.
- The app always has its bundled ARB strings as the final fallback: a missing, empty or unparsable remote
  string falls back to the language's fallback language, then to the bundled string. Remote data can never
  crash or blank the UI.
- Labels are display-only (no persisted strings), so no data migration is needed for existing rows.

### Flutter
- Cached bundle (disk) applied before first frame; background refresh on launch and on resume
  (at most every 15 min) via the languages list `version`; new strings rebuild the app live.
- Generated `RemoteAppLocalizations extends AppLocalizations` overrides every getter/method:
  remote string (formatted with a small ICU formatter) -> fallback language -> bundled string.
  A test fails when the generated wrapper is out of sync with `app_ar.arb`.
- Languages added in admin (e.g. `fr`) appear in the language picker; a new language with no bundled ARB
  uses its fallback language's bundled strings plus remote strings. Material/Cupertino widgets fall back to
  English localizations when Flutter has none for that code; direction comes from the language record.

### Admin UI
- **Languages**: add/enable/disable/set default/reorder, direction, fallback, coverage %, publish, rollback.
- **Labels editor**: search by key or text, filter (missing, draft changes, placeholders), base language
  side by side with the target, placeholder chips, inline edit, save draft, publish, ARB/CSV import & export.

## 2. Dashboard rebuild
- New shell: grouped nav (Monitor / Content / Localization / Growth), top bar with environment badge.
- **Overview**: KPIs (new users, active users, CV analyses, generated CVs, job matches, premium users),
  7/30-day trend, AI calls/errors/latency/cost from `ai_call_logs`, queue backlog + failed jobs.
- **System health**: queue depth & oldest job age, failed jobs (retry/forget), AI provider error rate and
  p95 latency, PDF render failures, recent errors.
- **Users**: search, detail, premium grant/revoke (existing actions), activity counts.
- Existing pages (templates, education, jobs, leads, analyses, generated CVs, notifications) move into the
  new shell unchanged in behaviour.

## 3. Ideas for later phases
1. Remote config & feature flags (toggle features per platform/version/percentage without release).
2. Force/soft update gate (min supported app version per platform) and maintenance mode banner.
3. In-app announcements & banners (scheduled, targeted by language/country/premium).
4. AI translation assist: "fill missing with AI" creates drafts only; a human publishes.
5. AI prompt & model management: edit prompt versions, pick provider/model per operation, A/B compare.
6. AI cost budgets with alerts (daily spend, error spikes) to email/Slack.
7. Funnel & retention analytics (install -> sign up -> first CV -> export -> premium), cohort tables.
8. Admin roles (owner / editor / translator / support) + 2FA + admin audit log of every change.
9. Support tools: user timeline, resend verification, delete-account requests, read-only CV preview.
10. Content scheduling: publish jobs/education/notifications at a set time; expiry reminders.
11. Notification analytics: delivery, open rate per campaign; saved audiences.
12. Data exports (CSV) for every table, and a weekly KPI email digest.
