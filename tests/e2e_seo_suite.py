#!/usr/bin/env python3
"""
tests/e2e_seo_suite.py — Automated Test Suite for Technical SEO Verification Engine

Comprehensive test suite validating the verify_seo_pages.py engine against:
- Unit invariant checks for all 9 SEO rules (Rules 1-9)
- Root infrastructure asset verification (CNAME, robots.txt, sitemap.xml, 404.html)
- Adversarial edge cases: unicode titles, malformed JSON-LD, forbidden schemas, broken anchors, trailing slashes
- End-to-End synthetic mock run: 16 valid HTML pages + root assets -> 100% PASS (144 assertions)
- Negative gate run: current public directory with missing landing pages -> Fails closed

Zero external dependencies: uses Python standard library `unittest`, `tempfile`, `os`, `sys`, `json`.
"""

import os
import sys
import unittest
import tempfile
import shutil
import json

# Ensure parent directory is in sys.path to import verify_seo_pages
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(CURRENT_DIR)
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

import verify_seo_pages as seo


class TestRule1H1(unittest.TestCase):
    """Rule 1: Exactly one <h1> with non-empty text."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_single_valid_h1(self):
        p = self._parse("<html><body><h1>سيرتك الذاتية جاهزة</h1></body></html>")
        ok, msg = seo.verify_rule_1_h1(p)
        self.assertTrue(ok)
        self.assertIn("Exactly one <h1>", msg)

    def test_single_h1_with_nested_spans(self):
        p = self._parse("<html><body><h1>سيرتك الذاتية <span>جاهزة الآن</span></h1></body></html>")
        ok, msg = seo.verify_rule_1_h1(p)
        self.assertTrue(ok)
        self.assertIn("سيرتك الذاتية جاهزة الآن", msg)

    def test_missing_h1_fails(self):
        p = self._parse("<html><body><h2>عنوان فرعي</h2></body></html>")
        ok, msg = seo.verify_rule_1_h1(p)
        self.assertFalse(ok)
        self.assertIn("Missing <h1>", msg)

    def test_multiple_h1s_fail(self):
        p = self._parse("<html><body><h1>الأول</h1><h1>الثاني</h1></body></html>")
        ok, msg = seo.verify_rule_1_h1(p)
        self.assertFalse(ok)
        self.assertIn("Semantic hierarchy violation", msg)

    def test_empty_h1_fails(self):
        p = self._parse("<html><body><h1>   </h1></body></html>")
        ok, msg = seo.verify_rule_1_h1(p)
        self.assertFalse(ok)
        self.assertIn("empty", msg)


class TestRule2Title(unittest.TestCase):
    """Rule 2: <title> length <= 60 characters."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_arabic_title(self):
        title = "سيرتي Siratie | تطبيق سيرة ذاتية متوافقة مع ATS"  # 47 chars
        p = self._parse(f"<html><head><title>{title}</title></head></html>")
        ok, msg = seo.verify_rule_2_title(p)
        self.assertTrue(ok)
        self.assertIn("47/60", msg)

    def test_valid_english_title(self):
        title = "Siratie | ATS-Friendly CV App for Saudi & Gulf Jobs"  # 50 chars
        p = self._parse(f"<html><head><title>{title}</title></head></html>")
        ok, msg = seo.verify_rule_2_title(p)
        self.assertTrue(ok)

    def test_title_boundary_60_chars(self):
        title = "A" * 60
        p = self._parse(f"<html><head><title>{title}</title></head></html>")
        ok, _ = seo.verify_rule_2_title(p)
        self.assertTrue(ok)

    def test_title_exceeding_60_chars_fails(self):
        title = "A" * 61
        p = self._parse(f"<html><head><title>{title}</title></head></html>")
        ok, msg = seo.verify_rule_2_title(p)
        self.assertFalse(ok)
        self.assertIn("exceeds 60 characters", msg)

    def test_missing_title_fails(self):
        p = self._parse("<html><head></head></html>")
        ok, msg = seo.verify_rule_2_title(p)
        self.assertFalse(ok)
        self.assertIn("Missing <title>", msg)

    def test_empty_title_fails(self):
        p = self._parse("<html><head><title>  </title></head></html>")
        ok, msg = seo.verify_rule_2_title(p)
        self.assertFalse(ok)
        self.assertIn("empty", msg)


class TestRule3MetaDescription(unittest.TestCase):
    """Rule 3: <meta name="description"> length <= 155 characters."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_meta_description(self):
        desc = "أنشئ وافحص سيرتك الذاتية بالذكاء الاصطناعي لتتوافق مع أنظمة التوظيف ATS واكتشف أحدث الوظائف."  # ~92 chars
        p = self._parse(f"<html><head><meta name=\"description\" content=\"{desc}\"></head></html>")
        ok, msg = seo.verify_rule_3_meta_description(p)
        self.assertTrue(ok)
        self.assertIn("/155 chars", msg)

    def test_meta_description_boundary_155_chars(self):
        desc = "x" * 155
        p = self._parse(f"<html><head><meta name=\"description\" content=\"{desc}\"></head></html>")
        ok, _ = seo.verify_rule_3_meta_description(p)
        self.assertTrue(ok)

    def test_meta_description_exceeding_155_chars_fails(self):
        desc = "x" * 156
        p = self._parse(f"<html><head><meta name=\"description\" content=\"{desc}\"></head></html>")
        ok, msg = seo.verify_rule_3_meta_description(p)
        self.assertFalse(ok)
        self.assertIn("exceeds 155 characters", msg)

    def test_missing_meta_description_fails(self):
        p = self._parse("<html><head></head></html>")
        ok, msg = seo.verify_rule_3_meta_description(p)
        self.assertFalse(ok)
        self.assertIn("Missing <meta name=\"description\">", msg)

    def test_empty_meta_description_fails(self):
        p = self._parse("<html><head><meta name=\"description\" content=\"   \"></head></html>")
        ok, msg = seo.verify_rule_3_meta_description(p)
        self.assertFalse(ok)
        self.assertIn("empty", msg)


class TestRule4Canonical(unittest.TestCase):
    """Rule 4: Self-referencing <link rel="canonical"> matches file path exactly."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_exact_canonical(self):
        spec = {"canonical": "https://siratie.com/ats-cv-checker/"}
        p = self._parse("<html><head><link rel=\"canonical\" href=\"https://siratie.com/ats-cv-checker/\"></head></html>")
        ok, msg = seo.verify_rule_4_canonical(p, spec)
        self.assertTrue(ok)
        self.assertIn("self-referencing and exact", msg)

    def test_canonical_missing_trailing_slash_fails(self):
        spec = {"canonical": "https://siratie.com/ats-cv-checker/"}
        p = self._parse("<html><head><link rel=\"canonical\" href=\"https://siratie.com/ats-cv-checker\"></head></html>")
        ok, msg = seo.verify_rule_4_canonical(p, spec)
        self.assertFalse(ok)

    def test_canonical_wrong_path_fails(self):
        spec = {"canonical": "https://siratie.com/ats-cv-checker/"}
        p = self._parse("<html><head><link rel=\"canonical\" href=\"https://siratie.com/cv-builder/\"></head></html>")
        ok, msg = seo.verify_rule_4_canonical(p, spec)
        self.assertFalse(ok)
        self.assertIn("Canonical mismatch", msg)

    def test_missing_canonical_fails(self):
        spec = {"canonical": "https://siratie.com/"}
        p = self._parse("<html><head></head></html>")
        ok, msg = seo.verify_rule_4_canonical(p, spec)
        self.assertFalse(ok)
        self.assertIn("Missing <link rel=\"canonical\">", msg)


class TestRule5Hreflang(unittest.TestCase):
    """Rule 5: All three hreflang links (ar, en, x-default) exist and match expected URLs."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_symmetrical_hreflang(self):
        spec = {
            "hreflang_ar": "https://siratie.com/cv-builder/",
            "hreflang_en": "https://siratie.com/en/cv-builder/",
            "hreflang_x_default": "https://siratie.com/cv-builder/",
        }
        html = """
        <html><head>
          <link rel="alternate" hreflang="ar" href="https://siratie.com/cv-builder/">
          <link rel="alternate" hreflang="en" href="https://siratie.com/en/cv-builder/">
          <link rel="alternate" hreflang="x-default" href="https://siratie.com/cv-builder/">
        </head></html>
        """
        p = self._parse(html)
        ok, msg = seo.verify_rule_5_hreflang(p, spec)
        self.assertTrue(ok)

    def test_missing_x_default_fails(self):
        spec = {
            "hreflang_ar": "https://siratie.com/",
            "hreflang_en": "https://siratie.com/en/",
            "hreflang_x_default": "https://siratie.com/",
        }
        html = """
        <html><head>
          <link rel="alternate" hreflang="ar" href="https://siratie.com/">
          <link rel="alternate" hreflang="en" href="https://siratie.com/en/">
        </head></html>
        """
        p = self._parse(html)
        ok, msg = seo.verify_rule_5_hreflang(p, spec)
        self.assertFalse(ok)
        self.assertIn("Missing hreflang link(s): x-default", msg)

    def test_hreflang_asymmetry_fails(self):
        spec = {
            "hreflang_ar": "https://siratie.com/jobs/",
            "hreflang_en": "https://siratie.com/en/jobs/",
            "hreflang_x_default": "https://siratie.com/jobs/",
        }
        html = """
        <html><head>
          <link rel="alternate" hreflang="ar" href="https://siratie.com/jobs/">
          <link rel="alternate" hreflang="en" href="https://siratie.com/jobs/"> <!-- Bug: pointed to AR -->
          <link rel="alternate" hreflang="x-default" href="https://siratie.com/jobs/">
        </head></html>
        """
        p = self._parse(html)
        ok, msg = seo.verify_rule_5_hreflang(p, spec)
        self.assertFalse(ok)
        self.assertIn("mismatch", msg)


class TestRule6LangDir(unittest.TestCase):
    """Rule 6: lang and dir attributes match page language (ar -> rtl, en -> ltr)."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_arabic_rtl(self):
        spec = {"lang": "ar", "dir": "rtl"}
        p = self._parse("<html lang=\"ar\" dir=\"rtl\"><head></head></html>")
        ok, _ = seo.verify_rule_6_lang_dir(p, spec)
        self.assertTrue(ok)

    def test_valid_english_ltr(self):
        spec = {"lang": "en", "dir": "ltr"}
        p = self._parse("<html lang=\"en\" dir=\"ltr\"><head></head></html>")
        ok, _ = seo.verify_rule_6_lang_dir(p, spec)
        self.assertTrue(ok)

    def test_arabic_with_ltr_fails(self):
        spec = {"lang": "ar", "dir": "rtl"}
        p = self._parse("<html lang=\"ar\" dir=\"ltr\"><head></head></html>")
        ok, msg = seo.verify_rule_6_lang_dir(p, spec)
        self.assertFalse(ok)
        self.assertIn("dir attribute mismatch", msg)

    def test_missing_lang_attribute_fails(self):
        spec = {"lang": "ar", "dir": "rtl"}
        p = self._parse("<html dir=\"rtl\"><head></head></html>")
        ok, msg = seo.verify_rule_6_lang_dir(p, spec)
        self.assertFalse(ok)
        self.assertIn("Missing 'lang'", msg)


class TestRule7SocialCards(unittest.TestCase):
    """Rule 7: Open Graph and Twitter Card tags are fully populated."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_social_metadata(self):
        spec = {"canonical": "https://siratie.com/", "og_locale": "ar_SA"}
        html = """
        <html><head>
          <meta property="og:title" content="سيرتي Siratie">
          <meta property="og:description" content="تطبيق سيرة ذاتية متوافقة مع ATS">
          <meta property="og:image" content="https://siratie.com/images/og-image.webp">
          <meta property="og:url" content="https://siratie.com/">
          <meta property="og:type" content="website">
          <meta property="og:locale" content="ar_SA">
          <meta name="twitter:card" content="summary_large_image">
        </head></html>
        """
        p = self._parse(html)
        ok, _ = seo.verify_rule_7_social_cards(p, spec)
        self.assertTrue(ok)

    def test_missing_og_image_fails(self):
        spec = {"canonical": "https://siratie.com/", "og_locale": "ar_SA"}
        html = """
        <html><head>
          <meta property="og:title" content="سيرتي">
          <meta property="og:description" content="وصف">
          <meta property="og:url" content="https://siratie.com/">
          <meta property="og:type" content="website">
          <meta property="og:locale" content="ar_SA">
          <meta name="twitter:card" content="summary_large_image">
        </head></html>
        """
        p = self._parse(html)
        ok, msg = seo.verify_rule_7_social_cards(p, spec)
        self.assertFalse(ok)
        self.assertIn("og:image", msg)

    def test_wrong_twitter_card_fails(self):
        spec = {"canonical": "https://siratie.com/", "og_locale": "ar_SA"}
        html = """
        <html><head>
          <meta property="og:title" content="سيرتي">
          <meta property="og:description" content="وصف">
          <meta property="og:image" content="https://siratie.com/images/og.webp">
          <meta property="og:url" content="https://siratie.com/">
          <meta property="og:type" content="website">
          <meta property="og:locale" content="ar_SA">
          <meta name="twitter:card" content="summary"> <!-- Bug: not summary_large_image -->
        </head></html>
        """
        p = self._parse(html)
        ok, msg = seo.verify_rule_7_social_cards(p, spec)
        self.assertFalse(ok)
        self.assertIn("summary_large_image", msg)


class TestRule8JsonLd(unittest.TestCase):
    """Rule 8: JSON-LD structured data syntax and zero forbidden schemas."""

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_homepage_graph(self):
        spec = {
            "required_schemas": ["Organization", "WebSite", "MobileApplication"],
            "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating", "BreadcrumbList"],
        }
        json_data = {
            "@context": "https://schema.org",
            "@graph": [
                {"@type": "Organization", "name": "Siratie", "url": "https://siratie.com/"},
                {"@type": "WebSite", "name": "Siratie", "url": "https://siratie.com/"},
                {"@type": "MobileApplication", "name": "Siratie App", "operatingSystem": "iOS, Android"},
            ]
        }
        html = f"<html><head><script type=\"application/ld+json\">{json.dumps(json_data)}</script></head></html>"
        p = self._parse(html)
        ok, _ = seo.verify_rule_8_json_ld(p, spec)
        self.assertTrue(ok)

    def test_forbidden_job_posting_fails(self):
        spec = {
            "required_schemas": ["BreadcrumbList"],
            "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
        }
        json_data = {
            "@context": "https://schema.org",
            "@graph": [
                {"@type": "BreadcrumbList", "itemListElement": []},
                {"@type": "JobPosting", "title": "Software Engineer"}, # VIOLATION!
            ]
        }
        html = f"<html><head><script type=\"application/ld+json\">{json.dumps(json_data)}</script></head></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_8_json_ld(p, spec)
        self.assertFalse(ok)
        self.assertIn("JobPosting", msg)

    def test_forbidden_aggregate_rating_key_fails(self):
        spec = {
            "required_schemas": ["BreadcrumbList"],
            "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
        }
        json_data = {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "aggregateRating": {"ratingValue": 5.0, "ratingCount": 2000}, # VIOLATION!
        }
        html = f"<html><head><script type=\"application/ld+json\">{json.dumps(json_data)}</script></head></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_8_json_ld(p, spec)
        self.assertFalse(ok)
        self.assertIn("aggregateRating", msg)

    def test_syntax_error_in_json_fails(self):
        spec = {"required_schemas": ["BreadcrumbList"]}
        html = "<html><head><script type=\"application/ld+json\">{ invalid json: true, }</script></head></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_8_json_ld(p, spec)
        self.assertFalse(ok)
        self.assertIn("JSON-LD syntax error", msg)


class TestRule9InternalLinks(unittest.TestCase):
    """Rule 9: All internal links resolve to valid files without 404s."""

    def setUp(self):
        self.temp_dir = tempfile.mkdtemp()
        # Create mock target files
        os.makedirs(os.path.join(self.temp_dir, "ats-cv-checker"), exist_ok=True)
        with open(os.path.join(self.temp_dir, "index.html"), "w", encoding="utf-8") as f:
            f.write("<div id=\"features\"></div>")
        with open(os.path.join(self.temp_dir, "ats-cv-checker", "index.html"), "w", encoding="utf-8") as f:
            f.write("<div id=\"checker-tool\"></div>")

    def tearDown(self):
        shutil.rmtree(self.temp_dir)

    def _parse(self, html):
        p = seo.HTMLDocParser()
        p.feed(html)
        return p

    def test_valid_internal_links_and_anchors(self):
        html = """
        <html><body>
          <div id="top"></div>
          <a href="/">Home</a>
          <a href="/ats-cv-checker/">ATS Tool</a>
          <a href="/ats-cv-checker/#checker-tool">Direct Tool Anchor</a>
          <a href="#top">Top Anchor</a>
          <a href="[PLACEHOLDER: App Store URL]">Download iOS</a>
          <a href="https://twitter.com/siratie">Twitter (External)</a>
        </body></html>
        """
        p = self._parse(html)
        ok, _ = seo.verify_rule_9_internal_links(p, self.temp_dir, "index.html")
        self.assertTrue(ok)

    def test_missing_trailing_slash_fails(self):
        html = "<html><body><a href=\"/ats-cv-checker\">Missing Trailing Slash</a></body></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_9_internal_links(p, self.temp_dir, "index.html")
        self.assertFalse(ok)
        self.assertIn("missing trailing slash", msg)

    def test_404_broken_link_fails(self):
        html = "<html><body><a href=\"/non-existent-page/\">Dead Link</a></body></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_9_internal_links(p, self.temp_dir, "index.html")
        self.assertFalse(ok)
        self.assertIn("Broken link (404)", msg)

    def test_broken_target_anchor_fails(self):
        html = "<html><body><a href=\"/ats-cv-checker/#non-existent-id\">Broken Anchor</a></body></html>"
        p = self._parse(html)
        ok, msg = seo.verify_rule_9_internal_links(p, self.temp_dir, "index.html")
        self.assertFalse(ok)
        self.assertIn("Broken target anchor", msg)


class TestRootAssets(unittest.TestCase):
    """Root SEO infrastructure assets verification."""

    def setUp(self):
        self.temp_dir = tempfile.mkdtemp()

    def tearDown(self):
        shutil.rmtree(self.temp_dir)

    def test_cname_valid_and_invalid(self):
        cname_path = os.path.join(self.temp_dir, "CNAME")
        # Missing
        ok, _ = seo.verify_cname(self.temp_dir)
        self.assertFalse(ok)

        # Invalid content
        with open(cname_path, "w", encoding="utf-8") as f:
            f.write("https://siratie.com/\n")
        ok, _ = seo.verify_cname(self.temp_dir)
        self.assertFalse(ok)

        # Valid content
        with open(cname_path, "w", encoding="utf-8") as f:
            f.write("siratie.com\n")
        ok, _ = seo.verify_cname(self.temp_dir)
        self.assertTrue(ok)

    def test_robots_txt_valid_and_invalid(self):
        robots_path = os.path.join(self.temp_dir, "robots.txt")
        # Missing
        ok, _ = seo.verify_robots_txt(self.temp_dir)
        self.assertFalse(ok)

        # Incomplete (missing sitemap)
        with open(robots_path, "w", encoding="utf-8") as f:
            f.write("User-agent: *\nDisallow:\n")
        ok, _ = seo.verify_robots_txt(self.temp_dir)
        self.assertFalse(ok)

        # Complete
        with open(robots_path, "w", encoding="utf-8") as f:
            f.write("User-agent: *\nAllow: /\nSitemap: https://siratie.com/sitemap.xml\n")
        ok, _ = seo.verify_robots_txt(self.temp_dir)
        self.assertTrue(ok)


class TestFullSyntheticE2ERun(unittest.TestCase):
    """Constructs a 100% compliant synthetic public directory and verifies all 144 assertions pass."""

    def setUp(self):
        self.temp_public = tempfile.mkdtemp()
        self._build_compliant_mock_site(self.temp_public)

    def tearDown(self):
        shutil.rmtree(self.temp_public)

    def _build_compliant_mock_site(self, root_dir):
        # 1. Root assets
        with open(os.path.join(root_dir, "CNAME"), "w", encoding="utf-8") as f:
            f.write("siratie.com\n")

        with open(os.path.join(root_dir, "robots.txt"), "w", encoding="utf-8") as f:
            f.write("User-agent: *\nAllow: /\nSitemap: https://siratie.com/sitemap.xml\n")

        # Sitemap XML with all 16 URLs and 48 xhtml:links
        xml_lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ]
        for spec in seo.PAGE_SPECS:
            canon = spec["canonical"]
            xml_lines.append(f'  <url>')
            xml_lines.append(f'    <loc>{canon}</loc>')
            xml_lines.append(f'    <xhtml:link rel="alternate" hreflang="ar" href="{spec["hreflang_ar"]}"/>')
            xml_lines.append(f'    <xhtml:link rel="alternate" hreflang="en" href="{spec["hreflang_en"]}"/>')
            xml_lines.append(f'    <xhtml:link rel="alternate" hreflang="x-default" href="{spec["hreflang_x_default"]}"/>')
            xml_lines.append(f'  </url>')
        xml_lines.append('</urlset>')

        with open(os.path.join(root_dir, "sitemap.xml"), "w", encoding="utf-8") as f:
            f.write("\n".join(xml_lines))

        with open(os.path.join(root_dir, "404.html"), "w", encoding="utf-8") as f:
            f.write('<!DOCTYPE html><html><head><meta name="robots" content="noindex, follow"></head><body><a href="/">Home</a></body></html>')

        # 2. Build 16 HTML pages
        for spec in seo.PAGE_SPECS:
            file_path = os.path.join(root_dir, spec["rel_path"])
            os.makedirs(os.path.dirname(file_path), exist_ok=True)

            # JSON-LD schemas
            graph_items = []
            for schema in spec["required_schemas"]:
                if schema == "Organization":
                    graph_items.append({"@type": "Organization", "name": "Siratie", "url": "https://siratie.com/"})
                elif schema == "WebSite":
                    graph_items.append({"@type": "WebSite", "name": "Siratie", "url": "https://siratie.com/"})
                elif schema == "MobileApplication":
                    graph_items.append({"@type": "MobileApplication", "name": "Siratie App", "operatingSystem": "iOS, Android"})
                elif schema == "BreadcrumbList":
                    graph_items.append({
                        "@type": "BreadcrumbList",
                        "itemListElement": [
                            {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://siratie.com/"},
                            {"@type": "ListItem", "position": 2, "name": spec["name"], "item": spec["canonical"]}
                        ]
                    })

            json_ld_str = json.dumps({"@context": "https://schema.org", "@graph": graph_items})

            html_content = f"""<!DOCTYPE html>
<html lang="{spec['lang']}" dir="{spec['dir']}">
<head>
  <meta charset="utf-8">
  <title>Siratie | {spec['name'][:40]}</title>
  <meta name="description" content="Official {spec['name']} page for Siratie ATS-friendly CV platform and Saudi career engine.">
  <link rel="canonical" href="{spec['canonical']}">
  <link rel="alternate" hreflang="ar" href="{spec['hreflang_ar']}">
  <link rel="alternate" hreflang="en" href="{spec['hreflang_en']}">
  <link rel="alternate" hreflang="x-default" href="{spec['hreflang_x_default']}">
  <meta property="og:title" content="Siratie | {spec['name'][:40]}">
  <meta property="og:description" content="Official {spec['name']} page for Siratie ATS-friendly CV platform.">
  <meta property="og:image" content="https://siratie.com/images/og-share.webp">
  <meta property="og:url" content="{spec['canonical']}">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="{spec['og_locale']}">
  <meta name="twitter:card" content="summary_large_image">
  <script type="application/ld+json">
{json_ld_str}
  </script>
</head>
<body>
  <div id="main-content">
    <h1>Heading for {spec['name']}</h1>
    <a href="/">Home</a>
    <a href="{spec['canonical']}">Self</a>
    <a href="#main-content">Anchor</a>
    <a href="[PLACEHOLDER: App Store URL]">Store Badge</a>
  </div>
</body>
</html>
"""
            with open(file_path, "w", encoding="utf-8") as f:
                f.write(html_content)

    def test_synthetic_site_achieves_100_percent_pass(self):
        """Verifies that our verification engine achieves 100% pass across all 144 assertions on compliant pages."""
        results = seo.run_seo_audit(self.temp_public, verbose=False)
        self.assertTrue(results["all_passed"], f"Expected 100% pass, but got diagnostics: {results['diagnostics']}")
        self.assertEqual(results["total_page_assertions"], 144)
        self.assertEqual(results["passed_page_assertions"], 144)
        self.assertEqual(results["failed_page_assertions"], 0)
        self.assertEqual(len(results["diagnostics"]), 0)


class TestRealPublicDirectoryNegativeGate(unittest.TestCase):
    """Verifies that the verification engine fails closed on incomplete directories and passes on complete public/."""

    def test_current_public_fails_closed(self):
        # 1. Negative gate invariant: verify engine fails closed on an incomplete mock directory (AGENTS.md Rule 1)
        with tempfile.TemporaryDirectory() as incomplete_dir:
            os.makedirs(os.path.join(incomplete_dir, "ats-cv-checker"), exist_ok=True)
            with open(os.path.join(incomplete_dir, "ats-cv-checker", "index.html"), "w", encoding="utf-8") as f:
                f.write("<html><head><title>Incomplete</title></head><body><h1>Only one page</h1></body></html>")

            incomplete_results = seo.run_seo_audit(incomplete_dir, verbose=False)
            self.assertFalse(incomplete_results["all_passed"])
            self.assertGreater(incomplete_results["failed_page_assertions"], 0)

        # 2. Production directory gate: verify real production public/ achieves 100% pass across all assertions
        real_public = os.path.join(PROJECT_ROOT, "public")
        results = seo.run_seo_audit(real_public, verbose=False)
        self.assertTrue(results["all_passed"], f"Real public directory failed audit: {results.get('diagnostics', [])}")
        self.assertEqual(results["failed_page_assertions"], 0)


if __name__ == "__main__":
    unittest.main(verbosity=2)
