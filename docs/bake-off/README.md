# Phase 6 — Arabic quality bake-off

Time-boxed experiment: does Claude produce better Arabic CV *analysis advice* than `gpt-4.1-mini` for Sirati users?

## Pre-committed decision rule

Agreed **before** looking at results:

1. Present outputs **blind**, randomised arm order.
2. Native Arabic speaker familiar with Gulf/MENA hiring scores each arm 1–5 on:
   - factual accuracy (no invention)
   - Arabic register / fluency (formal MSA)
   - actionability
   - RTL/LTR handling of technical terms
3. **Switch production only if Claude wins clearly** on accuracy or fluency.
4. Narrow / ambiguous win → **stay on OpenAI** and **remove** the Claude driver.
5. Production default remains `CV_AI_PROVIDER=openai` until an explicit switch is recorded in `storage/app/bake-off/DECISION.md`.

Cost is already settled: gpt-4.1-mini is ~2.8× cheaper.

## Run

```bash
# Both providers (requires OPENAI_API_KEY + ANTHROPIC_API_KEY)
php artisan ai:arabic-bake-off --providers=both

# Subset
php artisan ai:arabic-bake-off --limit=10 --providers=openai
```

Outputs land in `storage/app/bake-off/`:

| Path | Audience |
|------|----------|
| `blind/*.json` | Human rater |
| `score_sheet_BLIND.csv` | Human rater |
| `score_sheet_KEY.csv` | Experimenter only |
| `raw/*.json` | Experimenter only |
| `DECISION.md` | Decision record |

## Wiring

- `App\Services\ClaudeCvService` implements `CvAiProvider`
- `CV_AI_PROVIDER=openai|claude` (default **openai**)
- Response cache decorator wraps whichever driver is selected
