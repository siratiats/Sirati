# Project rules for coding agents (Grok, Antigravity, Claude)

Sirati: Flutter 3.44 / Dart 3.12 mobile app (Arabic RTL + English) for ATS CV scanning, AI CV generation, jobs and education; backend is the Laravel API at https://siratie.com/api (repo D:\Sirati, not this one).
State: Riverpod 3. Layers: `lib/core` -> `lib/shared` -> `lib/features/<feature>/{application,data,domain,presentation}`; features never import each other (except app, dashboard, localization).
Checks: `flutter analyze` (must say "No issues found") and `flutter test` (run only related tests). Also follow the workspace rules in `../AGENTS.md`.

## Hard rules
- Never read, print, copy or edit `.env` files, keys, or anything listed in `.agents/protected-paths.txt`.
- Never change `AGENTS.md`, `.gitignore` or anything in `.agents/` (the safety setup).
- Never run `git push`, delete files or data, stop running processes, call external services, or publish anything.
- Stay inside the task. If something outside it looks wrong, mention it in your summary instead of changing it.

## Code style
- Match the surrounding code: naming, structure, comment density, error handling.
- Don't add dependencies unless necessary; say why when you do.

## Before you finish
1. Run the project's type check / build / tests (see README or package scripts) and fix what you broke.
2. End with a short summary: files changed, commands run and their results, anything undone or uncertain.