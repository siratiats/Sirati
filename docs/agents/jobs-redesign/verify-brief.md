# Verify fixes (READ-ONLY, diff only, <= 250 words)

Your previous findings were addressed. Updated diffs: `docs/agents/jobs-redesign/diff-backend.patch`, `docs/agents/jobs-redesign/diff-flutter.patch`.

Fixes made:
- Extractor rewritten line-based (`ApplyLinkExtractor::fromMessageHtml($html)`): only links on lines kept after footer stripping; a link counts if its line has an apply cue, else only for known apply hosts (gov.sa, jadarat, ATS, forms) or careers/jobs/apply paths. More shorteners denied (incl. lnkd.in, clck.ru) on server and client; client also rejects /search and /hashtag paths.
- Footer: contact-bearing lines never cut; promo openers narrowed (no bare انضم/شارك/قناة/اشترك); separator signature cut only for one short line starting with an emoji/symbol or holding a promo token.
- applyInstructions: weak cues (التواصل عبر/على, للتواصل, التقديم على, to apply) need a contact or WhatsApp mention in the block; "التواصل مع" removed.
- Dedupe: fingerprint anchored on company, else on apply destination, else null (never merged); conflicting destinations never merge. Re-fetch never drops a stored link (email-only refetch keeps URL).
- Matched preset: CV-target-only users no longer produce `CASE ELSE` / `ORDER BY 0` (tier order omitted); `has_profile`/`job_title_name` use job title or CV target title.
- Client: deadline chip only when `valid_until_label` is present; share never sends the source (Telegram) link; hero = best eligible of the top 3.
- Tests added for each (generated inputs). Laravel job tests 51/51, Flutter job tests 160/160, analyzer clean.

Confirm each of YOUR earlier findings is fixed, and list any NEW defect introduced by these fixes (file:line + failing input). End with `VERDICT: LGTM` or `VERDICT: CHANGES`.
