# Sirati Workspace Engineering Rules

All agents and contributors working in the Sirati repository must adhere to the following invariants:

## 1. Test Invariants, Not Review Examples
- When writing or updating tests in response to a bug or code review finding, **never calibrate tests merely to pass the specific strings, numbers, or examples cited in the report**.
- Test the general invariant and behavior class across arbitrary valid inputs, adversarial cases, and boundary conditions (e.g. involutive round-trips, arbitrary job titles, multi-word Latin-Arabic combinations).

## 2. Strict Quality Gates (Zero Tolerance Slack)
- Contrast assertions must be strict mathematical evaluations (e.g. `expect(ratio, greaterThanOrEqualTo(4.5))`). Do not introduce artificial offsets (e.g. `+0.05` leeway).
- If a token fails contrast under certain conditions, fix the palette or component architecture—do not dilute the test assertion.

## 3. Semantic & Architectural Validation
- Never use regular expressions over Dart/PHP source code to guess AST or widget hierarchies (source regex is blind to local variables, custom wrapper widgets, parameters passed as children, and window offsets).
- For architectural constraints:
  - Either eliminate the invalid token/pattern at the root (delete dead or unsafe tokens),
  - Or validate at runtime / widget-pump level using real component trees.

## 4. Directionality & Text Boundaries
- Never use orthographic heuristics on Arabic text to guess if it is visual or logical. Enforce clear boundary contracts where normalization occurs exactly once at the ingestion/extraction boundary.

## 6. A Deliverable Is Not Done Until Something Calls It — and the Call Chain Is Traced to the User-Visible End
- For any ticket that adds a utility, catalog, or test suite, name the production call site or CI job that consumes it in the Definition of Done.
- If there is none yet, the ticket is infrastructure — say so on the ticket rather than marking it complete.
- Then follow the value to where a user sees it. For anything the user exports, downloads, or sends onward, the acceptance test asserts on that artefact, not only on the layer that produces it.
- When a ticket localizes a screen, check whether any of its strings are persisted. A displayed string is a one-line catalog change; a stored one is a schema change with a migration behind it.

## 7. Security Gates Must Fail Closed (No Optional Configuration Bypasses)
- A gate or authorization check that can be disabled by a missing environment variable is not a gate until it fails closed.
- Outside local development, if a required secret, token, or security configuration is unset or empty, the system must **fail closed loudly** (e.g., HTTP 503 with a critical log alert), never fall through to permissive or unauthenticated processing.
