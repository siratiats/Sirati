# Project: Siratie Bilingual Static Landing Site

## Architecture
- **Root Directory**: `d:\Sirati\public`
- **Output Paradigm**: Pure static HTML/CSS/JS without build server dependencies. Each URL resolves via a dedicated folder containing `index.html` ensuring clean trailing slashes.
- **Bilingual Architecture**:
  - Arabic (RTL, `lang="ar"`, `dir="rtl"`): Root paths e.g. `siratie.com/`, `siratie.com/ats-cv-checker/`, etc.
  - English (LTR, `lang="en"`, `dir="ltr"`): `/en/` prefixed paths e.g. `siratie.com/en/`, `siratie.com/en/ats-cv-checker/`, etc.
  - Symmetrical `hreflang` alternate links (`ar`, `en`, `x-default`) on every page.
- **Design System & Styling**:
  - Unified stylesheet `public/assets/css/site.css` using CSS Logical Properties (`margin-inline`, `padding-inline`, `border-inline-start`, `text-align: start`).
  - Colors based on `SiratiColors`: Primary `#007F73` (fill), Primary Ink `#006A60` (WCAG AA text on light `#FAF7F2`), Dark Theme background `#121614` with card surface `#1A201D`.
  - Typography: `IBM Plex Sans Arabic` for Arabic (weights 400, 600, 700; line-height 1.4-1.65), `Inter` / `Plus Jakarta Sans` for English.
- **Components**:
  - Smart Download Component: User-Agent detection (Android, iOS/iPadOS, Desktop), pre-launch state with "قريباً" / "Coming soon", GA4 telemetry events, crawlable semantic `<a>` tags.
  - Bilingual Header: Sticky header with SVG logo, desktop navigation, language switcher, mobile hamburger menu.
  - Site Footer: 4-column layout (Product, Features, Legal, Company), social links, copyright.
  - JSON-LD Structured Data: Valid Schema.org graph before `</head>`. Zero forbidden schemas (`JobPosting`, `HowTo`, unverified `aggregateRating`).

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Directory & URL Architecture | 16 dedicated directories with `index.html` for clean trailing slashes | M1, M2, M3, M4 | R1 |
| 2 | Root SEO Files | `CNAME`, `robots.txt`, `sitemap.xml` (with 48 `xhtml:link` hreflang), `404.html` | M1 | R1 |
| 3 | Core Design System & Tokens | `site.css` with luxury light/dark palettes, WCAG AA contrast, CSS logical properties | M1 | Survey 1 |
| 4 | Shared Assets & Favicon Suite | Multi-res favicons, SVG icon sprite, WebP mockups with explicit dimensions and alt | M1 | Survey 1 |
| 5 | Smart Download Component | User-Agent detection (Android, iOS, Desktop QR), pre-launch state, crawlable `<a>` | M1, M2, M3 | R2 |
| 6 | GA4 Telemetry Integration | GA4 script with `[PLACEHOLDER: Measurement ID]`, custom event schemas | M1, M2, M3 | R2 |
| 7 | Homepage Arabic (P1) | `/` title ≤ 60, meta ≤ 155, exact H1, 6 H2 sections, keywords, download component | M2 | R4 (P1) |
| 8 | Homepage English (P2) | `/en/` title ≤ 60, meta ≤ 155, exact H1, 6 H2 sections, keywords, download component | M2 | R4 (P2) |
| 9 | Dedicated Download Arabic (P11) | `/download/` QR code, store badges, OS requirements, download component | M2 | R4 (P11) |
| 10 | Dedicated Download English (P12) | `/en/download/` QR code, store badges, OS requirements, download component | M2 | R4 (P12) |
| 11 | ATS CV Checker Arabic (P3) | `/ats-cv-checker/` exact H1, 5 H2 sections, ATS checker feature preview | M3 | R4 (P3) |
| 12 | ATS CV Checker English (P4) | `/en/ats-cv-checker/` exact H1, 5 H2 sections, ATS checker feature preview | M3 | R4 (P4) |
| 13 | CV Builder Arabic (P5) | `/cv-builder/` exact H1, 4 H2 sections, CV builder showcase preview | M3 | R4 (P5) |
| 14 | CV Builder English (P6) | `/en/cv-builder/` exact H1, 4 H2 sections, CV builder showcase preview | M3 | R4 (P6) |
| 15 | CV Templates Arabic (P7) | `/cv-templates/` exact H1, 4 H2 sections, CV template cards & preview | M3 | R4 (P7) |
| 16 | CV Templates English (P8) | `/en/cv-templates/` exact H1, 4 H2 sections, CV template cards & preview | M3 | R4 (P8) |
| 17 | Saudi Jobs Arabic (P9) | `/jobs/` exact H1, 4 H2 sections, Saudi job board preview | M3 | R4 (P9) |
| 18 | Saudi Jobs English (P10) | `/en/jobs/` exact H1, 4 H2 sections, Saudi job board preview | M3 | R4 (P10) |
| 19 | Privacy Policy Arabic (P13) | `/privacy/` Saudi PDPL compliance, NO download component | M4 | R4 (P13) |
| 20 | Privacy Policy English (P14) | `/en/privacy/` Saudi PDPL compliance, NO download component | M4 | R4 (P14) |
| 21 | Terms of Use Arabic (P15) | `/terms/` Saudi terms of service, NO download component | M4 | R4 (P15) |
| 22 | Terms of Use English (P16) | `/en/terms/` Saudi terms of service, NO download component | M4 | R4 (P16) |
| 23 | Schema.org JSON-LD Graph | Organization + WebSite on `/`, `/en/`; MobileApplication on P1, P2, P11, P12; Breadcrumbs on P3-P16 | M2, M3, M4 | R3 |
| 24 | Objective Python Test Suite | `d:\Sirati\verify_seo_pages.py` executing 9 rules across 16 pages (144 assertions) | M-TEST | Verification |
| 25 | E2E Test Suite Tiers 1-4 | Category-partition, boundary, pairwise, and application tests publishing `TEST_READY.md` | M-TEST | E2E Track |
| 26 | Final Gate & Coverage Hardening | 100% pass on verify_seo_pages.py, zero 404s, forensic audit clean | M5 | Final Gate |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M-TEST | E2E Test Infrastructure & SEO Verifier | Implement `d:\Sirati\verify_seo_pages.py`, test runners, and publish `TEST_READY.md` | none | DONE |
| M1 | Foundation, Root SEO & Shared Assets | `CNAME`, `robots.txt`, `sitemap.xml`, `404.html`, `site.css`, `download.js`, SVGs, favicons, WebP assets | none | DONE |
| M2 | Core Conversion Pages (P1, P2, P11, P12) | Homepage & Download pages (AR + EN), Schema JSON-LD (Org, WebSite, MobileApp), Smart Download | M1 | DONE |
| M3 | Feature Showcase Pages (P3 - P10) | ATS Checker, CV Builder, Templates, Jobs (AR + EN), Breadcrumbs, feature previews | M1 | DONE |
| M4 | Legal Pages & Navigation Integrity (P13 - P16) | Privacy & Terms (AR + EN), PDPL compliance, full internal link mesh without 404s | M1 | DONE |
| M5 | Final Verification & Audit Gate | Execute `verify_seo_pages.py`, pass all tests, forensic audit gate | M-TEST, M2, M3, M4 | DONE |

## Interface Contracts
### Design System ↔ HTML Pages
- CSS stylesheet path: `/assets/css/site.css`
- Core classes: `.container`, `.btn`, `.btn-primary`, `.badge-coming-soon`, `.card`, `.section-title`, `.grid-2`, `.grid-3`, `.grid-4`
- Theme attributes: `data-theme="light"` (default) or `data-theme="dark"`
- Logical spacing: Uses logical properties (`margin-block`, `margin-inline`, `padding-block`, `padding-inline`)

### Smart Download Component Contract
- Component container: `<div class="smart-download-container" data-lang="ar|en">`
- Anchor tags:
  - iOS link: `<a href="[PLACEHOLDER: App Store URL]" class="store-badge ios-badge" data-store="ios">...</a>`
  - Android link: `<a href="[PLACEHOLDER: Google Play URL]" class="store-badge android-badge" data-store="android">...</a>`
  - Desktop QR container: `<div class="desktop-qr-container">...</div>`
  - Pre-launch badge: `<span class="badge-status">قريباً</span>` (AR) / `<span class="badge-status">Coming soon</span>` (EN)
- Telemetry script: `/assets/js/download.js` detects `navigator.userAgent`, toggles platform visibility, logs GA4 events (`download_click_android`, `download_click_ios`, `download_click_desktop`, `qr_view`).

### Header & Symmetrical Bilingual Switcher
- Symmetrical URL mapping:
  - `/` <-> `/en/`
  - `/[slug]/` <-> `/en/[slug]/`
- Language switcher link: `<a href="[ALTERNATE_URL]" class="lang-switch" hreflang="[ALT_LANG]" data-lang="[ALT_LANG]">English / العربية</a>`

### JSON-LD Graph Contract
- Location: strictly before `</head>` in a single `<script type="application/ld+json">` tag.
- Graph object format: `{"@context": "https://schema.org", "@graph": [...]}`
- Forbidden: `JobPosting`, `HowTo`, unverified `aggregateRating`.

## Code Layout & File Ownership
- **Milestone M-TEST**:
  - `d:\Sirati\verify_seo_pages.py`
  - `d:\Sirati\tests\e2e_seo_suite.py`
  - `d:\Sirati\TEST_READY.md`
- **Milestone M1**:
  - `d:\Sirati\public\CNAME`
  - `d:\Sirati\public\robots.txt`
  - `d:\Sirati\public\sitemap.xml`
  - `d:\Sirati\public\404.html`
  - `d:\Sirati\public\assets\css\site.css`
  - `d:\Sirati\public\assets\js\download.js`
  - `d:\Sirati\public\assets\icons\sprite.svg`
  - `d:\Sirati\public\assets\images\` (WebP mockups)
  - `d:\Sirati\public\favicon.ico`, `favicon.svg`, `apple-touch-icon.png`
- **Milestone M2**:
  - `d:\Sirati\public\index.html` (P1)
  - `d:\Sirati\public\en\index.html` (P2)
  - `d:\Sirati\public\download\index.html` (P11)
  - `d:\Sirati\public\en\download\index.html` (P12)
- **Milestone M3**:
  - `d:\Sirati\public\ats-cv-checker\index.html` (P3)
  - `d:\Sirati\public\en\ats-cv-checker\index.html` (P4)
  - `d:\Sirati\public\cv-builder\index.html` (P5)
  - `d:\Sirati\public\en\cv-builder\index.html` (P6)
  - `d:\Sirati\public\cv-templates\index.html` (P7)
  - `d:\Sirati\public\en\cv-templates\index.html` (P8)
  - `d:\Sirati\public\jobs\index.html` (P9)
  - `d:\Sirati\public\en\jobs\index.html` (P10)
- **Milestone M4**:
  - `d:\Sirati\public\privacy\index.html` (P13)
  - `d:\Sirati\public\en\privacy\index.html` (P14)
  - `d:\Sirati\public\terms\index.html` (P15)
  - `d:\Sirati\public\en\terms\index.html` (P16)
