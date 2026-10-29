# Post-implementation review: jobs redesign + apply links (READ-ONLY)

Claude implemented the plan from your earlier pre-design review. Review the DIFF ONLY; do not edit files, run the app, or explore beyond what a finding needs.
Answer in <= 400 words: findings ranked by severity with file:line, each with a concrete failing input. End with `VERDICT: LGTM` or `VERDICT: CHANGES`.

Diffs:
- Backend: `docs/agents/jobs-redesign/diff-backend.patch`
- Flutter (nested repo `flutter_app/`): `docs/agents/jobs-redesign/diff-flutter.patch`

## What was decided (from the three reviews)
- Apply method `url | email | none`. Links only from the Telegram message-text element; deny Telegram, social, WhatsApp, shorteners (not resolved: SSRF), hashtag searches, non-https, IP hosts, credentials/ports; LinkedIn only `/jobs/`. Email from text. Phone/WhatsApp numbers are never an apply action; instead the post's "how to apply" lines (`apply_instructions`) are shown as text.
- Same rules re-applied in the API payload (`ApplyLinkExtractor::resolve`) and in Flutter (`ApplyDestination.of`), so stored/cached Telegram links can never be "Apply". Telegram post stays as `source_url`, shown only as "View original post".
- Promo footers stripped at ingest; `jobs:repair-listings` (run by the migration) cleans old rows; re-fetch never drops a stored apply destination.
- Dedupe across channels by `fingerprint` (normalised title + company + city; null for short titles); the repost's apply link fills the original if it had none.
- No invented employer ("جهة سعودية معتمدة"), no inferred "verified", no "Verified Job" badge for general posts, deadline label only for curated rows (aggregated rows retire after 21 days internally).
- Match % removed everywhere (payload, model, UI). "مناسبة لي" = role-only filter (job title / category / CV target title), ordered by tier then same city then remote, before the LIMIT; no role => empty + prompt to add a job title.
- Region filter (central/eastern/western/northern/southern) replaces city + category chips. Hero only for a <48h post with an allowed link; its button really applies and names the site. "New" = <48h.
- Detail: how-to-apply card, sticky bottom bar (Apply / Apply by email / else Tailor CV primary), share, SelectionArea.

## Check especially
1. Any path where a denied host, `mailto` injection, or the Telegram post can still be opened as "Apply" (server or client, fresh or cached payloads).
2. `applyMatchedFilter` raw ORDER BY bindings vs the caller's ordering; SQL portability (MySQL prod, SQLite tests).
3. `persistAggregatedJob` dedupe/update logic: wrong merges, lost updates, notification behaviour.
4. Footer stripping eating real job text; `applyInstructions` false positives.
5. RTL/a11y/contrast regressions in the new widgets; anything from your pre-design review that was missed.
