# Test Readiness Specification: Siratie Bilingual Static Landing Site (Milestone M-TEST)

Published by: **E2E Test Writer (Specialist & QA)**  
Date: **2026-10-10**  
Target Directory: `d:\Sirati\public`  
Verification Tools: `d:\Sirati\verify_seo_pages.py` & `d:\Sirati\tests\e2e_seo_suite.py`  

---

## 1. Executive Summary & Verification Gate Status

The test verification infrastructure for the Siratie (سيرتي) bilingual static landing site project is fully implemented, verified, and operational.
The test suite operates with **zero external dependencies** using Python's standard library (`os`, `sys`, `re`, `json`, `argparse`, `urllib.parse`, `html.parser`, `xml.etree.ElementTree`).

The audit engine enforces a **zero-tolerance quality gate**:
- Exactly **144 page-level assertions** across 16 static HTML pages (9 rules per page).
- **4 root infrastructure assertions** (`CNAME`, `robots.txt`, `sitemap.xml`, `404.html`).
- Total: **148 automated assertions**.
- Exit code `0` on 100% pass; non-zero exit code (`1`) on any failure with actionable failure diagnostics.

---

## 2. Test Runner Commands

### Primary SEO Audit Runner
```bash
# Standard audit run (scans d:\Sirati\public)
python verify_seo_pages.py

# Verbose audit run (logs every passing rule detail)
python verify_seo_pages.py -v

# Machine-readable JSON output (clean stdout for CI pipelines / jq)
python verify_seo_pages.py --json

# Custom public directory (for mock fixtures or stage testing)
python verify_seo_pages.py --public-dir path/to/public
```

### Comprehensive Unit & Adversarial Test Suite
```bash
# Runs the full test suite (unit checks, edge cases, synthetic 100% pass, and negative gate)
python tests/e2e_seo_suite.py
```

---

## 3. The 9 Technical SEO Verification Rules

| Rule # | Rule Name | Specification & Invariant | Pass Criterion | Failure Modes |
|:---:|---|---|---|---|
| **Rule 1** | Single H1 Tag | Every page must contain exactly one `<h1>` tag in the body. | `len(h1_tags) == 1` and `len(h1_text.strip()) > 0`. | Missing `<h1>`, multiple `<h1>` tags, or whitespace-only `<h1>`. |
| **Rule 2** | Page Title Length | `<title>` in `<head>` must not exceed 60 Unicode characters. | `0 < len(title_text) <= 60`. | Missing title, empty title, or title > 60 chars (causes SERP truncation). |
| **Rule 3** | Meta Description Length | `<meta name="description">` content must not exceed 155 Unicode characters. | `0 < len(meta_desc) <= 155`. | Missing meta description, empty content, or length > 155 chars. |
| **Rule 4** | Self-Referencing Canonical | `<link rel="canonical" href="...">` must exactly match the page's canonical URL. | `href == expected_canonical`, starts with `https://siratie.com/`, ends with `/`. | Missing canonical tag, wrong URL, missing trailing slash, or protocol mismatch. |
| **Rule 5** | Symmetrical Hreflang Triplet | Must contain all 3 alternate links (`ar`, `en`, `x-default`) matching reciprocal URLs. | All 3 present, ending with `/`, with `x-default` targeting Arabic version. | Missing `hreflang`, target URL mismatch, or `x-default` pointing to English. |
| **Rule 6** | HTML `lang` & `dir` Attributes | Root `<html>` element must declare language and direction aligned with page. | Arabic: `lang="ar*"` & `dir="rtl"`. English: `lang="en*"` & `dir="ltr"`. | Missing attributes, direction mismatch (e.g. `ar` with `dir="ltr"`). |
| **Rule 7** | Social Graph Metadata | Open Graph and Twitter Card tags must be fully populated. | `og:title`, `og:description`, `og:image`, `og:url` (matches canonical), `og:locale` (`ar_SA`/`en_US`), `og:type="website"`, `twitter:card="summary_large_image"`. | Missing tags, `og:url != canonical`, `twitter:card` not `summary_large_image`. |
| **Rule 8** | JSON-LD Structured Data & Forbidden Schemas | Valid Schema.org JSON-LD graph with required schemas and zero black-hat schemas. | Valid JSON syntax; required schemas present; ZERO occurrences of `JobPosting`, `HowTo`, or `aggregateRating`. | Syntax error, missing required schema, or inclusion of any forbidden schema. |
| **Rule 9** | Internal Links & Zero 404s | All internal `<a href="...">` links must resolve to existing files on disk. | Resolves to physical file; directories end with `/`; target anchor IDs exist in document. | Missing file (404), missing trailing slash on directory, broken anchor ID. |

---

## 4. Root Infrastructure Assets Requirements

1. **`CNAME`**:
   - Location: `public/CNAME`.
   - Content: Exact apex domain `siratie.com\n`.
2. **`robots.txt`**:
   - Location: `public/robots.txt`.
   - Content: Must declare `User-agent: *`, `Allow: /`, and `Sitemap: https://siratie.com/sitemap.xml`.
3. **`sitemap.xml`**:
   - Location: `public/sitemap.xml`.
   - Content: Valid XML declaring all 16 `<url>` entries, each containing 3 `<xhtml:link>` elements (`ar`, `en`, `x-default`). Total 48 alternate links.
4. **`404.html`**:
   - Location: `public/404.html`.
   - Content: Standalone custom error page containing `<meta name="robots" content="noindex, follow">` and navigation return link to homepage (`/` or `/en/`).

---

## 5. 16-Page Coverage & Assertion Matrix

| # | Page ID | Name | Language / Dir | Canonical URL | Required Schemas | Forbidden Schemas | Assertions |
|:---:|:---:|---|:---:|---|---|---|:---:|
| 1 | **P1** | Arabic Homepage | `ar` / `rtl` | `https://siratie.com/` | `Organization`, `WebSite`, `MobileApplication` | `JobPosting`, `HowTo`, `aggregateRating`, `BreadcrumbList` | 9 |
| 2 | **P2** | English Homepage | `en` / `ltr` | `https://siratie.com/en/` | `Organization`, `WebSite`, `MobileApplication` | `JobPosting`, `HowTo`, `aggregateRating`, `BreadcrumbList` | 9 |
| 3 | **P3** | Arabic ATS CV Checker | `ar` / `rtl` | `https://siratie.com/ats-cv-checker/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 4 | **P4** | English ATS CV Checker | `en` / `ltr` | `https://siratie.com/en/ats-cv-checker/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 5 | **P5** | Arabic CV Builder | `ar` / `rtl` | `https://siratie.com/cv-builder/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 6 | **P6** | English CV Builder | `en` / `ltr` | `https://siratie.com/en/cv-builder/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 7 | **P7** | Arabic CV Templates | `ar` / `rtl` | `https://siratie.com/cv-templates/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 8 | **P8** | English CV Templates | `en` / `ltr` | `https://siratie.com/en/cv-templates/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 9 | **P9** | Arabic Jobs Aggregator | `ar` / `rtl` | `https://siratie.com/jobs/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 10 | **P10** | English Jobs Aggregator | `en` / `ltr` | `https://siratie.com/en/jobs/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 11 | **P11** | Arabic Download | `ar` / `rtl` | `https://siratie.com/download/` | `MobileApplication`, `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 12 | **P12** | English Download | `en` / `ltr` | `https://siratie.com/en/download/` | `MobileApplication`, `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 13 | **P13** | Arabic Privacy Policy | `ar` / `rtl` | `https://siratie.com/privacy/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 14 | **P14** | English Privacy Policy | `en` / `ltr` | `https://siratie.com/en/privacy/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 15 | **P15** | Arabic Terms of Use | `ar` / `rtl` | `https://siratie.com/terms/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| 16 | **P16** | English Terms of Use | `en` / `ltr` | `https://siratie.com/en/terms/` | `BreadcrumbList` | `JobPosting`, `HowTo`, `aggregateRating` | 9 |
| — | **Root** | Root Assets (`CNAME`, `robots.txt`, `sitemap.xml`, `404.html`) | — | — | — | — | 4 |
| **Total** | — | **Full Site Suite** | — | — | — | — | **148** |

---

## 6. Current Baseline Execution Results

### 1. Test Suite (`tests/e2e_seo_suite.py`)
- **Total Tests Run**: 42
- **Pass Count**: 42 / 42 (100% pass)
- **Execution Time**: ~0.15s
- **Verified Areas**:
  - Individual rule unit assertions (Rules 1-9).
  - Adversarial negative cases (syntax errors, forbidden schemas, unicode bounds, broken anchors, missing trailing slashes).
  - Root assets parsing and validation.
  - End-to-end synthetic mock run with 16 fully compliant pages (144 assertions pass).
  - Negative gate run ensuring incomplete directories fail closed.

### 2. Live Directory Verification (`python verify_seo_pages.py`)
- **Root Assets**: 4/4 Verified (`CNAME`, `robots.txt`, `sitemap.xml`, `404.html` deployed by M1).
- **16 Static Pages**: Correctly identified as not yet generated (M2, M3, M4 milestones pending).
- **Gate Outcome**: Fails closed with exit code `1` and actionable diagnostic list naming each missing page file.

---

## 7. Definition of Done for Milestones M2, M3, M4 & Final Gate M5

1. Implementers create the respective HTML landing page files under `d:\Sirati\public`.
2. Execute `python verify_seo_pages.py`.
3. Acceptance Gate passes when and only when:
   - All 16 pages exist.
   - All 144 page assertions return `[PASS]`.
   - All 4 root assets return `[PASS]`.
   - Zero diagnostics in failure log.
   - Script terminates with `exit code 0`.
