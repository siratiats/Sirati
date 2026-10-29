# Final verification (READ-ONLY, diff only, <= 200 words)

Round-2 findings were fixed (updated `docs/agents/jobs-redesign/diff-backend.patch`, `diff-flutter.patch`):
- Promo-opener lines never yield Apply, even with a visible careers URL; a "المصدر:" line counts only via a careers-page link (careers.* subdomain or apply path).
- Cue words match whole words (`(?<!\p{L})و?…(?!\p{L})`; "المتقدمين" no longer matches); apply paths match whole segments (`/job-market-2026` does not).
- Phone detection uses `\p{Nd}` (Arabic-Indic digits kept).
- Destination identity: `JobNews::normalizeDestination` lowercases scheme/host only, drops www, trailing slash, fragment, utm_*/fbclid/gclid; path/query case kept. Conflict check uses it.
- Re-fetch: preserved destination is applied BEFORE fingerprinting, so identity survives a link-less refetch.
- Unlisted shorteners: very short host + single opaque segment is denied unless known apply host or careers path (server + client).
- Hero: card tap and Apply button are siblings, not nested.
Tests added for each; job suites green.

Confirm your open findings are fixed; report only NEW real defects (file:line + input). End with `VERDICT: LGTM` or `VERDICT: CHANGES`.
