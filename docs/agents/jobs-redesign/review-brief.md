# Pre-design review: Jobs area (أخبار الوظائف) redesign + logic

READ-ONLY review. Do not edit any file. Answer in <= 500 words, ranked recommendations, file:line where relevant.
Claude will implement; you are a reviewer giving the best design + logic direction BEFORE the work starts.

## Goal (from the product owner)
1. Best possible jobs screen (list + detail) for Saudi job seekers, Arabic RTL first, English supported.
2. Remove the matching-percentage logic ("% تطابق", match score bar, high-match styling) - not wanted.
3. Apply call-to-action must never send the user to Telegram. Verify and fix the apply-link logic.
4. Improve the overall jobs logic (ranking, filters, freshness, dedupe, trust).

## Code
- Backend feed: `app/Http/Controllers/MobileContentController.php` `jobNews()` (~L301), `jobNewsPayload()` (~L471: match_score, match_reasons, matched_skills, missing_skills, apply_url, url).
- Matcher: `app/Services/Jobs/JobProfileMatcherService.php` (calculateMatch, applyMatchedFilter for preset `matched`). Also used by `app/Services/Notifications/DailyNotificationCandidateService.php`.
- Ingestion: `app/Services/Jobs/SaudiJobAggregatorService.php` `aggregateTelegramFeed()` L121-259 (apply URL picking L177-189), Remotive/RSS below.
- Model: `app/Models/JobNews.php`.
- Flutter (nested repo `flutter_app/`): `lib/features/jobs/**` (job_news_screen, job_news_detail_screen, widgets/job_news_card, featured_job_card, job_news_filters_bar, profile_context_banner, domain/job_news_feed.dart), `lib/shared/models/job_news.dart` (`actionUrl` getter L119: falls back from apply_url to url).
- Current design board (the owner's latest mock): `docs/agents/jobs-redesign/current-design-board.jpg` (filters الكل / مناسبة لي / جهات حكومية / شركات / مهير وتدريب / عن بعد, region chips with SVG flags, hero card, list, skeleton, error, offline, empty-region states).

## Verified facts (Claude, live check of t.me/s/<channel> on 2026-10-29)
- 58/58 Telegram posts get a `t.me` apply_url: the picker takes the first href in the whole post HTML (the channel avatar link `https://t.me/<channel>`), and when none it falls back to `https://t.me/<post>`. Flutter `actionUrl` additionally falls back to `url` (the t.me post).
- Links inside the post text by channel:
  - ewdifh: real employer links (careers.*.com, jadarat.sa, taqeem.gov.sa, linkedin.com) but also x.com and lik.ad (shortener).
  - jobs2ksa: every post ends with the same promo footer (instagram, snapchat, chat.whatsapp.com, t.me); only 1/20 has a real form (forms.gle).
  - jobmag: links are hashtag searches (`?q=%23...`); 13/20 posts say to apply by email; 1 forms.cloud.microsoft.

## Questions - answer each
1. Apply-link extraction: exact rules you recommend (host deny/allow lists, shorteners, social/promo footers, hashtag links, linkedin, x.com, emails -> mailto, phone/WhatsApp numbers?). What should `apply_method` look like in the API (e.g. `url | email | none`) and what should the CTA do when there is none? Should Telegram ever be shown (e.g. as "source" only)? Also should promo footers be stripped from `body`?
2. Removing match %: what to delete vs keep (payload fields, `matched` preset "مناسبة لي", notifications use, tests). How should "مناسبة لي" rank without a visible score?
3. Jobs list + detail UX: the strongest layout for this audience (card content priority, hero card, filters/regions, save/share, "new" marker, deadline, verified badge, empty/error/offline states, accessibility, RTL pitfalls). What in the current board should change?
4. Logic improvements worth doing now (dedupe across channels, expiry instead of fixed +30 days, ranking, title/company extraction quality, data trust). Rank by user value vs effort.
5. Top risks and the tests that must exist (test invariants across arbitrary inputs, not just these examples - see AGENTS.md).
