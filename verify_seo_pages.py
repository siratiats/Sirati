#!/usr/bin/env python3
"""
verify_seo_pages.py — Standalone Technical SEO Verification Engine for Siratie (سيرتي)

Automated audit tool validating all 16 bilingual static HTML pages and root SEO assets
under d:\\Sirati\\public according to the 9 technical SEO rules defined in PROJECT.md
and survey_report.md.

Standard Library only: os, sys, re, json, argparse, urllib.parse, html.parser, xml.etree.ElementTree.
Exit code: 0 on 100% pass across all assertions; 1 on any failure.
"""

import os
import sys
import re
import json
import argparse
from urllib.parse import urlparse, unquote
from html.parser import HTMLParser
import xml.etree.ElementTree as ET

# Reconfigure stdout/stderr to utf-8 if needed on Windows
if sys.stdout and hasattr(sys.stdout, "reconfigure"):
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

# ANSI Colors for terminal output
GREEN = "\033[92m"
RED = "\033[91m"
YELLOW = "\033[93m"
CYAN = "\033[96m"
BOLD = "\033[1m"
RESET = "\033[0m"

# Enable virtual terminal processing on Windows if supported
if sys.platform == "win32":
    try:
        import ctypes
        kernel32 = ctypes.windll.kernel32
        kernel32.SetConsoleMode(kernel32.GetStdHandle(-11), 7)
    except Exception:
        pass


# ==============================================================================
# PAGE SPECIFICATIONS MATRIX (16 Pages: 8 Arabic RTL + 8 English LTR)
# ==============================================================================
PAGE_SPECS = [
    {
        "id": "P1",
        "name": "Arabic Homepage",
        "rel_path": "index.html",
        "url_path": "/",
        "canonical": "https://siratie.com/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/",
        "hreflang_en": "https://siratie.com/en/",
        "hreflang_x_default": "https://siratie.com/",
        "og_locale": "ar_SA",
        "required_schemas": ["Organization", "WebSite", "MobileApplication"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating", "BreadcrumbList"],
    },
    {
        "id": "P2",
        "name": "English Homepage",
        "rel_path": os.path.join("en", "index.html"),
        "url_path": "/en/",
        "canonical": "https://siratie.com/en/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/",
        "hreflang_en": "https://siratie.com/en/",
        "hreflang_x_default": "https://siratie.com/",
        "og_locale": "en_US",
        "required_schemas": ["Organization", "WebSite", "MobileApplication"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating", "BreadcrumbList"],
    },
    {
        "id": "P3",
        "name": "Arabic ATS CV Checker",
        "rel_path": os.path.join("ats-cv-checker", "index.html"),
        "url_path": "/ats-cv-checker/",
        "canonical": "https://siratie.com/ats-cv-checker/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/ats-cv-checker/",
        "hreflang_en": "https://siratie.com/en/ats-cv-checker/",
        "hreflang_x_default": "https://siratie.com/ats-cv-checker/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P4",
        "name": "English ATS CV Checker",
        "rel_path": os.path.join("en", "ats-cv-checker", "index.html"),
        "url_path": "/en/ats-cv-checker/",
        "canonical": "https://siratie.com/en/ats-cv-checker/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/ats-cv-checker/",
        "hreflang_en": "https://siratie.com/en/ats-cv-checker/",
        "hreflang_x_default": "https://siratie.com/ats-cv-checker/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P5",
        "name": "Arabic CV Builder",
        "rel_path": os.path.join("cv-builder", "index.html"),
        "url_path": "/cv-builder/",
        "canonical": "https://siratie.com/cv-builder/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/cv-builder/",
        "hreflang_en": "https://siratie.com/en/cv-builder/",
        "hreflang_x_default": "https://siratie.com/cv-builder/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P6",
        "name": "English CV Builder",
        "rel_path": os.path.join("en", "cv-builder", "index.html"),
        "url_path": "/en/cv-builder/",
        "canonical": "https://siratie.com/en/cv-builder/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/cv-builder/",
        "hreflang_en": "https://siratie.com/en/cv-builder/",
        "hreflang_x_default": "https://siratie.com/cv-builder/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P7",
        "name": "Arabic CV Templates",
        "rel_path": os.path.join("cv-templates", "index.html"),
        "url_path": "/cv-templates/",
        "canonical": "https://siratie.com/cv-templates/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/cv-templates/",
        "hreflang_en": "https://siratie.com/en/cv-templates/",
        "hreflang_x_default": "https://siratie.com/cv-templates/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P8",
        "name": "English CV Templates",
        "rel_path": os.path.join("en", "cv-templates", "index.html"),
        "url_path": "/en/cv-templates/",
        "canonical": "https://siratie.com/en/cv-templates/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/cv-templates/",
        "hreflang_en": "https://siratie.com/en/cv-templates/",
        "hreflang_x_default": "https://siratie.com/cv-templates/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P9",
        "name": "Arabic Jobs Aggregator",
        "rel_path": os.path.join("jobs", "index.html"),
        "url_path": "/jobs/",
        "canonical": "https://siratie.com/jobs/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/jobs/",
        "hreflang_en": "https://siratie.com/en/jobs/",
        "hreflang_x_default": "https://siratie.com/jobs/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P10",
        "name": "English Jobs Aggregator",
        "rel_path": os.path.join("en", "jobs", "index.html"),
        "url_path": "/en/jobs/",
        "canonical": "https://siratie.com/en/jobs/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/jobs/",
        "hreflang_en": "https://siratie.com/en/jobs/",
        "hreflang_x_default": "https://siratie.com/jobs/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P11",
        "name": "Arabic Download",
        "rel_path": os.path.join("download", "index.html"),
        "url_path": "/download/",
        "canonical": "https://siratie.com/download/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/download/",
        "hreflang_en": "https://siratie.com/en/download/",
        "hreflang_x_default": "https://siratie.com/download/",
        "og_locale": "ar_SA",
        "required_schemas": ["MobileApplication", "BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P12",
        "name": "English Download",
        "rel_path": os.path.join("en", "download", "index.html"),
        "url_path": "/en/download/",
        "canonical": "https://siratie.com/en/download/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/download/",
        "hreflang_en": "https://siratie.com/en/download/",
        "hreflang_x_default": "https://siratie.com/download/",
        "og_locale": "en_US",
        "required_schemas": ["MobileApplication", "BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P13",
        "name": "Arabic Privacy Policy",
        "rel_path": os.path.join("privacy", "index.html"),
        "url_path": "/privacy/",
        "canonical": "https://siratie.com/privacy/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/privacy/",
        "hreflang_en": "https://siratie.com/en/privacy/",
        "hreflang_x_default": "https://siratie.com/privacy/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P14",
        "name": "English Privacy Policy",
        "rel_path": os.path.join("en", "privacy", "index.html"),
        "url_path": "/en/privacy/",
        "canonical": "https://siratie.com/en/privacy/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/privacy/",
        "hreflang_en": "https://siratie.com/en/privacy/",
        "hreflang_x_default": "https://siratie.com/privacy/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P15",
        "name": "Arabic Terms of Use",
        "rel_path": os.path.join("terms", "index.html"),
        "url_path": "/terms/",
        "canonical": "https://siratie.com/terms/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/terms/",
        "hreflang_en": "https://siratie.com/en/terms/",
        "hreflang_x_default": "https://siratie.com/terms/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P16",
        "name": "English Terms of Use",
        "rel_path": os.path.join("en", "terms", "index.html"),
        "url_path": "/en/terms/",
        "canonical": "https://siratie.com/en/terms/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/terms/",
        "hreflang_en": "https://siratie.com/en/terms/",
        "hreflang_x_default": "https://siratie.com/terms/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P17",
        "name": "Arabic About Us",
        "rel_path": os.path.join("about", "index.html"),
        "url_path": "/about/",
        "canonical": "https://siratie.com/about/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/about/",
        "hreflang_en": "https://siratie.com/en/about/",
        "hreflang_x_default": "https://siratie.com/about/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList", "Organization"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P18",
        "name": "English About Us",
        "rel_path": os.path.join("en", "about", "index.html"),
        "url_path": "/en/about/",
        "canonical": "https://siratie.com/en/about/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/about/",
        "hreflang_en": "https://siratie.com/en/about/",
        "hreflang_x_default": "https://siratie.com/about/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList", "Organization"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P19",
        "name": "Arabic Contact Us",
        "rel_path": os.path.join("contact", "index.html"),
        "url_path": "/contact/",
        "canonical": "https://siratie.com/contact/",
        "lang": "ar",
        "dir": "rtl",
        "hreflang_ar": "https://siratie.com/contact/",
        "hreflang_en": "https://siratie.com/en/contact/",
        "hreflang_x_default": "https://siratie.com/contact/",
        "og_locale": "ar_SA",
        "required_schemas": ["BreadcrumbList", "ContactPage"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
    {
        "id": "P20",
        "name": "English Contact Us",
        "rel_path": os.path.join("en", "contact", "index.html"),
        "url_path": "/en/contact/",
        "canonical": "https://siratie.com/en/contact/",
        "lang": "en",
        "dir": "ltr",
        "hreflang_ar": "https://siratie.com/contact/",
        "hreflang_en": "https://siratie.com/en/contact/",
        "hreflang_x_default": "https://siratie.com/contact/",
        "og_locale": "en_US",
        "required_schemas": ["BreadcrumbList", "ContactPage"],
        "forbidden_schemas": ["JobPosting", "HowTo", "aggregateRating"],
    },
]


# ==============================================================================
# HTML PARSER (Zero-dependency Standard Library DOM Parser)
# ==============================================================================
class HTMLDocParser(HTMLParser):
    """Parses HTML document into structured elements for SEO rule assertions."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.html_attrs = {}
        self.title = None
        self._in_title = False
        self._title_chars = []

        self.h1_list = []
        self._h1_depth = 0
        self._h1_chars = []

        self.meta_tags = []      # list of dicts: {'name': ..., 'content': ..., 'property': ...}
        self.link_tags = []      # list of dicts: {'rel': ..., 'href': ..., 'hreflang': ...}
        self.a_tags = []         # list of dicts: {'href': ..., 'id': ..., 'text': ...}
        self.element_ids = set() # set of all element id attributes

        self.json_ld_raw = []    # list of raw JSON-LD text strings
        self._in_json_ld = False
        self._json_ld_chars = []

    def handle_starttag(self, tag, attrs):
        tag_lower = tag.lower()
        attr_dict = {k.lower(): v for k, v in attrs if k is not None}

        # Track element IDs
        if "id" in attr_dict and attr_dict["id"]:
            self.element_ids.add(attr_dict["id"])

        if tag_lower == "html":
            self.html_attrs = attr_dict
        elif tag_lower == "title":
            self._in_title = True
            self._title_chars = []
        elif tag_lower == "h1":
            self._h1_depth += 1
            if self._h1_depth == 1:
                self._h1_chars = []
        elif tag_lower == "meta":
            self.meta_tags.append(attr_dict)
        elif tag_lower == "link":
            self.link_tags.append(attr_dict)
        elif tag_lower == "a":
            self.a_tags.append(attr_dict)
        elif tag_lower == "script":
            script_type = attr_dict.get("type", "").lower()
            if script_type == "application/ld+json":
                self._in_json_ld = True
                self._json_ld_chars = []

    def handle_endtag(self, tag):
        tag_lower = tag.lower()
        if tag_lower == "title" and self._in_title:
            self.title = "".join(self._title_chars).strip()
            self._in_title = False
        elif tag_lower == "h1" and self._h1_depth > 0:
            self._h1_depth -= 1
            if self._h1_depth == 0:
                self.h1_list.append("".join(self._h1_chars).strip())
        elif tag_lower == "script" and self._in_json_ld:
            self.json_ld_raw.append("".join(self._json_ld_chars).strip())
            self._in_json_ld = False

    def handle_data(self, data):
        if self._in_title:
            self._title_chars.append(data)
        if self._h1_depth > 0:
            self._h1_chars.append(data)
        if self._in_json_ld:
            self._json_ld_chars.append(data)


def parse_html_file(file_path):
    """Reads and parses an HTML file. Returns HTMLDocParser instance or None if missing."""
    if not os.path.isfile(file_path):
        return None
    with open(file_path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    parser = HTMLDocParser()
    parser.feed(content)
    return parser


# ==============================================================================
# 9 VERIFICATION RULES IMPLEMENTATION
# ==============================================================================

def verify_rule_1_h1(parser):
    """Rule 1: Every page has exactly one <h1> with non-empty text."""
    if parser is None:
        return False, "File could not be parsed"
    count = len(parser.h1_list)
    if count == 0:
        return False, "Missing <h1> tag (count is 0)"
    if count > 1:
        return False, f"Semantic hierarchy violation: found {count} <h1> tags (expected exactly 1)"
    h1_text = parser.h1_list[0]
    if not h1_text:
        return False, "<h1> tag exists but contains empty or whitespace-only content"
    return True, f"Exactly one <h1>: '{h1_text[:40]}...'" if len(h1_text) > 40 else f"Exactly one <h1>: '{h1_text}'"


def verify_rule_2_title(parser):
    """Rule 2: <title> length <= 60 characters."""
    if parser is None:
        return False, "File could not be parsed"
    if parser.title is None:
        return False, "Missing <title> tag"
    title_text = parser.title
    length = len(title_text)
    if length == 0:
        return False, "<title> tag is empty"
    if length > 60:
        return False, f"<title> exceeds 60 characters: {length} chars ('{title_text}')"
    return True, f"<title> valid ({length}/60 chars): '{title_text}'"


def verify_rule_3_meta_description(parser):
    """Rule 3: <meta name="description"> length <= 155 characters."""
    if parser is None:
        return False, "File could not be parsed"
    desc_tag = None
    for m in parser.meta_tags:
        if m.get("name", "").lower() == "description":
            desc_tag = m
            break
    if desc_tag is None:
        return False, "Missing <meta name=\"description\"> tag"
    content = desc_tag.get("content", "").strip()
    length = len(content)
    if length == 0:
        return False, "<meta name=\"description\"> content is empty"
    if length > 155:
        return False, f"<meta name=\"description\"> exceeds 155 characters: {length} chars"
    return True, f"Meta description valid ({length}/155 chars)"


def verify_rule_4_canonical(parser, spec):
    """Rule 4: Self-referencing <link rel="canonical"> matches file path exactly."""
    if parser is None:
        return False, "File could not be parsed"
    canonical_links = []
    for link in parser.link_tags:
        rel = link.get("rel", "").lower()
        if "canonical" in rel.split():
            canonical_links.append(link.get("href", "").strip())
    if not canonical_links:
        return False, "Missing <link rel=\"canonical\"> tag"
    if len(canonical_links) > 1:
        return False, f"Multiple canonical tags found: {canonical_links}"
    actual = canonical_links[0]
    expected = spec["canonical"]
    if actual != expected:
        return False, f"Canonical mismatch: expected '{expected}', found '{actual}'"
    if not actual.endswith("/"):
        return False, f"Canonical URL missing trailing slash: '{actual}'"
    if not actual.startswith("https://siratie.com/"):
        return False, f"Canonical URL does not use https://siratie.com: '{actual}'"
    return True, f"Canonical tag self-referencing and exact: '{actual}'"


def verify_rule_5_hreflang(parser, spec):
    """Rule 5: All three hreflang links (ar, en, x-default) exist and match expected URLs."""
    if parser is None:
        return False, "File could not be parsed"
    hreflangs = {}
    for link in parser.link_tags:
        rel = link.get("rel", "").lower()
        if "alternate" in rel.split() and "hreflang" in link:
            hl = link["hreflang"].lower().strip()
            href = link.get("href", "").strip()
            hreflangs[hl] = href

    missing = []
    for required in ["ar", "en", "x-default"]:
        if required not in hreflangs:
            missing.append(required)
    if missing:
        return False, f"Missing hreflang link(s): {', '.join(missing)}"

    expected_map = {
        "ar": spec["hreflang_ar"],
        "en": spec["hreflang_en"],
        "x-default": spec["hreflang_x_default"],
    }
    for lang, expected_url in expected_map.items():
        actual_url = hreflangs[lang]
        if actual_url != expected_url:
            return False, f"hreflang='{lang}' URL mismatch: expected '{expected_url}', found '{actual_url}'"
        if not actual_url.endswith("/"):
            return False, f"hreflang='{lang}' URL missing trailing slash: '{actual_url}'"

    return True, "Symmetrical hreflang links (ar, en, x-default) verified"


def verify_rule_6_lang_dir(parser, spec):
    """Rule 6: lang and dir attributes match page language (ar -> rtl, en -> ltr)."""
    if parser is None:
        return False, "File could not be parsed"
    html_attrs = parser.html_attrs
    lang = html_attrs.get("lang", "").lower().strip()
    direction = html_attrs.get("dir", "").lower().strip()

    if not lang:
        return False, "Missing 'lang' attribute on <html> element"
    if not direction:
        return False, "Missing 'dir' attribute on <html> element"

    expected_lang = spec["lang"]
    expected_dir = spec["dir"]

    if not lang.startswith(expected_lang):
        return False, f"lang attribute mismatch: expected '{expected_lang}' (or '{expected_lang}-*'), found '{lang}'"
    if direction != expected_dir:
        return False, f"dir attribute mismatch: expected '{expected_dir}', found '{direction}'"

    return True, f"<html> attributes valid (lang='{lang}', dir='{direction}')"


def verify_rule_7_social_cards(parser, spec):
    """Rule 7: Open Graph and Twitter Card tags are fully populated."""
    if parser is None:
        return False, "File could not be parsed"
    meta_props = {}
    for m in parser.meta_tags:
        prop = (m.get("property") or m.get("name") or "").lower().strip()
        content = m.get("content", "").strip()
        if prop and content:
            meta_props[prop] = content

    # Check Open Graph tags
    required_og = ["og:title", "og:description", "og:image", "og:url", "og:type", "og:locale"]
    missing_og = [prop for prop in required_og if prop not in meta_props]
    if missing_og:
        return False, f"Missing required Open Graph tags: {', '.join(missing_og)}"

    # og:url must match canonical exactly
    if meta_props["og:url"] != spec["canonical"]:
        return False, f"og:url mismatch: expected '{spec['canonical']}', found '{meta_props['og:url']}'"

    # og:type must be 'website'
    if meta_props["og:type"].lower() != "website":
        return False, f"og:type is '{meta_props['og:type']}', expected 'website'"

    # og:locale must match spec
    expected_locale = spec["og_locale"]
    if meta_props["og:locale"] != expected_locale:
        return False, f"og:locale mismatch: expected '{expected_locale}', found '{meta_props['og:locale']}'"

    # Check Twitter card tags
    if "twitter:card" not in meta_props:
        return False, "Missing 'twitter:card' meta tag"
    if meta_props["twitter:card"].lower() != "summary_large_image":
        return False, f"twitter:card is '{meta_props['twitter:card']}', expected 'summary_large_image'"

    return True, "Open Graph & Twitter card metadata fully populated"


def _extract_all_schema_types(data):
    """Recursively collects all @type values from parsed JSON-LD."""
    types = set()
    def _traverse(obj):
        if isinstance(obj, dict):
            for k, v in obj.items():
                if k in ("@type", "type"):
                    if isinstance(v, str):
                        types.add(v)
                    elif isinstance(v, list):
                        for item in v:
                            if isinstance(item, str):
                                types.add(item)
                _traverse(v)
        elif isinstance(obj, list):
            for item in obj:
                _traverse(item)
    _traverse(data)
    return types


def _find_forbidden_schemas(data):
    """Recursively checks for forbidden schemas: JobPosting, HowTo, aggregateRating."""
    violations = []
    def _traverse(obj):
        if isinstance(obj, dict):
            for k, v in obj.items():
                if k.lower() == "aggregaterating":
                    violations.append("aggregateRating (detected as key)")
                if k in ("@type", "type"):
                    vals = [v] if isinstance(v, str) else (v if isinstance(v, list) else [])
                    for val in vals:
                        if isinstance(val, str):
                            v_lower = val.lower()
                            if "jobposting" in v_lower:
                                violations.append(f"JobPosting (found type '{val}')")
                            if "howto" in v_lower:
                                violations.append(f"HowTo (found type '{val}')")
                            if "aggregaterating" in v_lower:
                                violations.append(f"aggregateRating (found type '{val}')")
                _traverse(v)
        elif isinstance(obj, list):
            for item in obj:
                _traverse(item)
    _traverse(data)
    return violations


def verify_rule_8_json_ld(parser, spec):
    """Rule 8: JSON-LD scripts are syntactically valid JSON with zero forbidden schemas."""
    if parser is None:
        return False, "File could not be parsed"
    if not parser.json_ld_raw:
        return False, "Missing <script type=\"application/ld+json\"> structured data block"

    parsed_structures = []
    for idx, raw_text in enumerate(parser.json_ld_raw, start=1):
        if not raw_text:
            return False, f"JSON-LD script block #{idx} is empty"
        try:
            parsed = json.loads(raw_text)
            parsed_structures.append(parsed)
        except json.JSONDecodeError as err:
            return False, f"JSON-LD syntax error in block #{idx}: {err}"

    # Check for forbidden schemas across all parsed structures
    for parsed in parsed_structures:
        forbidden = _find_forbidden_schemas(parsed)
        if forbidden:
            return False, f"Strictly forbidden schema detected: {', '.join(forbidden)}"

    # Collect all types found
    all_types = set()
    for parsed in parsed_structures:
        all_types.update(_extract_all_schema_types(parsed))

    # Verify required schemas
    required_schemas = spec.get("required_schemas", [])
    missing_required = [req for req in required_schemas if req not in all_types]
    if missing_required:
        return False, f"Missing required Schema.org type(s): {', '.join(missing_required)} (found: {', '.join(all_types) or 'none'})"

    # Verify page-specific forbidden schemas
    page_forbidden = spec.get("forbidden_schemas", [])
    for forb in page_forbidden:
        if forb in all_types:
            return False, f"Page-specific forbidden schema detected: '{forb}'"

    return True, f"JSON-LD valid with required schemas ({', '.join(required_schemas)})"


def verify_rule_9_internal_links(parser, public_dir, current_rel_path, page_cache=None):
    """Rule 9: All internal links resolve to valid files without 404s."""
    if parser is None:
        return False, "File could not be parsed"
    if page_cache is None:
        page_cache = {}

    current_file_abs = os.path.join(public_dir, current_rel_path)
    current_dir_abs = os.path.dirname(current_file_abs)
    errors = []

    for a in parser.a_tags:
        href = a.get("href", "").strip()
        if not href:
            continue

        # Ignore placeholder tokens (e.g. [PLACEHOLDER: App Store URL])
        if href.startswith("[PLACEHOLDER:") or "[placeholder:" in href.lower():
            continue

        # Ignore non-http/internal protocols
        if href.startswith(("mailto:", "tel:", "javascript:", "sms:", "data:")):
            continue

        # Pure anchor link on current page
        if href.startswith("#"):
            if href == "#":
                continue
            anchor_id = href[1:]
            if anchor_id and anchor_id not in parser.element_ids:
                errors.append(f"Broken on-page anchor '{href}': element id='{anchor_id}' not found")
            continue

        # External domains other than siratie.com
        parsed = urlparse(href)
        if parsed.scheme in ("http", "https"):
            if parsed.netloc.lower() not in ("siratie.com", "www.siratie.com"):
                continue  # External link
            # Convert siratie.com URL to path
            target_path_str = parsed.path
        else:
            target_path_str = parsed.path

        target_fragment = parsed.fragment

        # Check trailing slash invariant for directory links
        # If target has no file extension, it must end with '/'
        clean_path = target_path_str.rstrip("/")
        last_segment = os.path.basename(clean_path)
        has_extension = "." in last_segment

        if not has_extension and target_path_str and not target_path_str.endswith("/"):
            errors.append(f"Internal link '{href}' missing trailing slash (must be '{target_path_str}/')")
            continue

        # Resolve physical target file path
        if target_path_str.startswith("/"):
            rel_to_public = unquote(target_path_str.lstrip("/"))
            if not rel_to_public or target_path_str == "/":
                resolved_file = os.path.join(public_dir, "index.html")
            elif target_path_str.endswith("/"):
                resolved_file = os.path.join(public_dir, rel_to_public, "index.html")
            else:
                resolved_file = os.path.join(public_dir, rel_to_public)
        else:
            # Relative path
            unquoted = unquote(target_path_str)
            if target_path_str.endswith("/"):
                resolved_file = os.path.abspath(os.path.join(current_dir_abs, unquoted, "index.html"))
            else:
                resolved_file = os.path.abspath(os.path.join(current_dir_abs, unquoted))

        # Check file existence
        if not os.path.exists(resolved_file):
            errors.append(f"Broken link (404): '{href}' -> target '{os.path.relpath(resolved_file, public_dir)}' does not exist")
            continue

        # If link has fragment, check that target anchor exists
        if target_fragment and target_fragment != "":
            target_parser = page_cache.get(resolved_file)
            if target_parser is None and os.path.isfile(resolved_file) and resolved_file.endswith(".html"):
                target_parser = parse_html_file(resolved_file)
                page_cache[resolved_file] = target_parser

            if target_parser and target_fragment not in target_parser.element_ids:
                errors.append(f"Broken target anchor '{href}': id='{target_fragment}' not found in '{os.path.relpath(resolved_file, public_dir)}'")

    if errors:
        return False, "; ".join(errors[:3]) + (f" (and {len(errors)-3} more)" if len(errors) > 3 else "")
    return True, f"All {len(parser.a_tags)} links resolve to valid targets without 404s"


def verify_rule_10_apple_compliance(parser, public_dir, current_rel_path):
    """Rule 10: Apple Developer & D-U-N-S Corporate Identity, Address, and Anti-Placeholder Invariant."""
    file_path = os.path.join(public_dir, current_rel_path)
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            content = f.read()
    except Exception as err:
        return False, f"Could not read {current_rel_path}: {err}"

    errors = []

    # 1. Exact Establishment Name
    if "Digital Pass Establishment" not in content:
        errors.append("Missing exact entity name 'Digital Pass Establishment'")

    # 2. Exact Commercial Registration (CR)
    if "7051100811" not in content:
        errors.append("Missing CR No. '7051100811'")

    # 3. Ownership Statement
    text_content = re.sub(r'<[^>]+>', ' ', content)
    norm_text = ' '.join(text_content.split())
    if "Siratie is owned and operated by Digital Pass Establishment" not in norm_text and "سيرتي مملوك ومُشغَّل من قِبل Digital Pass Establishment" not in norm_text:
        errors.append("Missing explicit ownership statement binding Siratie to Digital Pass Establishment")

    # 4. Active Contact Channels
    if "info@siratie.com" not in content:
        errors.append("Missing official contact email 'info@siratie.com'")

    # 5. Full Address on standard landing pages
    is_legal = any(k in current_rel_path for k in ["privacy", "terms"])
    if not is_legal:
        if "+966 59 189 0300" not in content and "+966591890300" not in content:
            errors.append("Missing phone '+966 59 189 0300'")
        if "32273" not in content or "7676" not in content:
            errors.append("Missing national postal code 32273 or additional number 7676")

    # 6. Anti-placeholder & Anti-coming-soon (enforced on all main conversion pages; download page displays coming soon status)
    if "[PLACEHOLDER" in content or "[placeholder" in content:
        errors.append("Contains unconfigured [PLACEHOLDER] token")

    is_download_page = "download" in current_rel_path.lower()
    if not is_download_page:
        # Strip permissible nav label "قريباً" / "coming soon" from content before evaluating anti-placeholder invariant
        check_content = re.sub(r'<[^>]*class="[^"]*nav-badge[^"]*"[^>]*>.*?</span>', '', content, flags=re.IGNORECASE)
        check_content = re.sub(r'\(?قريباً\)?', '', check_content)
        check_content = re.sub(r'\(?coming soon\)?', '', check_content, flags=re.IGNORECASE)
        if "قريباً" in check_content or "قريبا" in check_content:
            errors.append("Contains 'قريباً' placeholder badge")
        if "coming soon" in check_content.lower():
            errors.append("Contains 'coming soon' placeholder badge")

    if errors:
        return False, "; ".join(errors)
    return True, "Apple & D-U-N-S corporate identity, CR, address, and zero-placeholder invariants satisfied"



# ==============================================================================
# ROOT ASSETS VERIFICATION
# ==============================================================================

def verify_cname(public_dir):
    """Verifies public/CNAME exists and contains siratie.com."""
    cname_path = os.path.join(public_dir, "CNAME")
    if not os.path.isfile(cname_path):
        return False, "public/CNAME does not exist"
    with open(cname_path, "r", encoding="utf-8") as f:
        content = f.read().strip()
    if content != "siratie.com":
        return False, f"CNAME content mismatch: expected 'siratie.com', found '{content}'"
    return True, "CNAME verified ('siratie.com')"


def verify_robots_txt(public_dir):
    """Verifies public/robots.txt exists and contains sitemap URL and crawler access."""
    robots_path = os.path.join(public_dir, "robots.txt")
    if not os.path.isfile(robots_path):
        return False, "public/robots.txt does not exist"
    with open(robots_path, "r", encoding="utf-8") as f:
        content = f.read()

    lines = [line.strip() for line in content.splitlines() if line.strip() and not line.strip().startswith("#")]
    content_lower = content.lower()

    if "sitemap: https://siratie.com/sitemap.xml" not in content_lower:
        return False, "robots.txt missing 'Sitemap: https://siratie.com/sitemap.xml'"
    if "user-agent:" not in content_lower:
        return False, "robots.txt missing 'User-agent:' directive"
    if "allow: /" not in content_lower:
        return False, "robots.txt missing 'Allow: /' directive"

    return True, "robots.txt verified with Sitemap directive and crawl permissions"


def verify_sitemap_xml(public_dir, page_specs):
    """Verifies public/sitemap.xml has all 16 URLs and 48 xhtml:link alternates."""
    sitemap_path = os.path.join(public_dir, "sitemap.xml")
    if not os.path.isfile(sitemap_path):
        return False, "public/sitemap.xml does not exist"

    try:
        tree = ET.parse(sitemap_path)
        root = tree.getroot()
    except ET.ParseError as err:
        return False, f"sitemap.xml XML syntax error: {err}"

    def strip_ns(tag):
        return tag.split("}")[-1] if "}" in tag else tag

    url_elements = [elem for elem in root if strip_ns(elem.tag) == "url"]
    expected_url_count = len(page_specs)
    if len(url_elements) != expected_url_count:
        return False, f"sitemap.xml contains {len(url_elements)} <url> entries (expected exactly {expected_url_count})"

    sitemap_locs = {}
    total_xhtml_links = 0

    for u in url_elements:
        loc = None
        xhtml_links = {}
        for child in u:
            tag_name = strip_ns(child.tag)
            if tag_name == "loc":
                loc = (child.text or "").strip()
            elif tag_name == "link":
                rel = child.attrib.get("rel", "").lower()
                hl = child.attrib.get("hreflang", "").lower()
                href = child.attrib.get("href", "").strip()
                if rel == "alternate" and hl:
                    xhtml_links[hl] = href
                    total_xhtml_links += 1

        if loc:
            sitemap_locs[loc] = xhtml_links

    # Check that all canonical URLs are present
    expected_canonicals = {spec["canonical"] for spec in page_specs}
    missing_locs = expected_canonicals - set(sitemap_locs.keys())
    if missing_locs:
        return False, f"sitemap.xml missing canonical URL(s): {', '.join(missing_locs)}"

    # Check total alternate links (3 per URL: ar, en, x-default)
    expected_xhtml_links = len(page_specs) * 3
    if total_xhtml_links != expected_xhtml_links:
        return False, f"sitemap.xml contains {total_xhtml_links} xhtml:link alternates (expected exactly {expected_xhtml_links}: 3 per page)"

    for spec in page_specs:
        canon = spec["canonical"]
        alternates = sitemap_locs.get(canon, {})
        for req in ["ar", "en", "x-default"]:
            if req not in alternates:
                return False, f"sitemap.xml URL '{canon}' missing xhtml:link hreflang='{req}'"

    return True, f"sitemap.xml verified ({expected_url_count} URLs, {expected_xhtml_links} xhtml:link alternates, valid XML)"


def verify_404_page(public_dir):
    """Verifies public/404.html exists, has noindex, and navigation links."""
    p404_path = os.path.join(public_dir, "404.html")
    if not os.path.isfile(p404_path):
        return False, "public/404.html does not exist"

    parser = parse_html_file(p404_path)
    if parser is None:
        return False, "public/404.html could not be parsed"

    # Check for noindex meta tag
    has_noindex = False
    for m in parser.meta_tags:
        if m.get("name", "").lower() == "robots":
            content = m.get("content", "").lower()
            if "noindex" in content:
                has_noindex = True
                break
    if not has_noindex:
        return False, "404.html missing <meta name=\"robots\" content=\"noindex, ...\"> tag"

    # Check for homepage link
    has_home_link = False
    for a in parser.a_tags:
        href = a.get("href", "").strip()
        if href in ("/", "/en/", "https://siratie.com/", "https://siratie.com/en/"):
            has_home_link = True
            break
    if not has_home_link:
        return False, "404.html missing navigation return link to homepage ('/' or '/en/')"

    return True, "404.html verified with noindex meta tag and homepage return link"


# ==============================================================================
# AUDIT RUNNER & DIAGNOSTICS REPORT
# ==============================================================================

def run_seo_audit(public_dir, verbose=False, quiet=False):
    """
    Executes the full audit across all 16 pages and root assets.
    Returns:
        dict: Summary results and diagnostics.
    """
    results = {
        "public_dir": os.path.abspath(public_dir),
        "total_page_assertions": 0,
        "passed_page_assertions": 0,
        "failed_page_assertions": 0,
        "pages": {},
        "root_assets": {},
        "all_passed": True,
        "diagnostics": [],
    }

    page_cache = {}

    def log(msg=""):
        if not quiet:
            print(msg)

    log(f"\n{BOLD}{CYAN}====================================================================={RESET}")
    log(f"{BOLD}{CYAN}   SIRATIE (سيرتي) TECHNICAL SEO & ZERO-SLACK VERIFICATION ENGINE   {RESET}")
    log(f"{BOLD}{CYAN}====================================================================={RESET}")
    log(f"Target Public Directory: {BOLD}{os.path.abspath(public_dir)}{RESET}\n")

    # 1. VERIFY ROOT ASSETS
    log(f"{BOLD}--- Root Infrastructure SEO Assets ---{RESET}")
    root_checks = [
        ("CNAME", verify_cname(public_dir)),
        ("robots.txt", verify_robots_txt(public_dir)),
        ("sitemap.xml", verify_sitemap_xml(public_dir, PAGE_SPECS)),
        ("404.html", verify_404_page(public_dir)),
    ]

    for asset_name, (ok, msg) in root_checks:
        results["root_assets"][asset_name] = {"passed": ok, "message": msg}
        if ok:
            log(f"  {GREEN}[PASS]{RESET} {asset_name:<14} : {msg}")
        else:
            results["all_passed"] = False
            results["diagnostics"].append(f"Root Asset [{asset_name}]: {msg}")
            log(f"  {RED}[FAIL]{RESET} {asset_name:<14} : {msg}")

    # 2. VERIFY ALL HTML PAGES (10 RULES EACH)
    log(f"\n{BOLD}--- {len(PAGE_SPECS)} Static Landing Pages (10 Rules / {len(PAGE_SPECS) * 10} Assertions) ---{RESET}")

    rules_meta = [
        (1, "H1 Tag Count", verify_rule_1_h1),
        (2, "Title <= 60", verify_rule_2_title),
        (3, "Meta Desc <= 155", verify_rule_3_meta_description),
        (4, "Canonical URL", verify_rule_4_canonical),
        (5, "Hreflang Triplet", verify_rule_5_hreflang),
        (6, "HTML Lang & Dir", verify_rule_6_lang_dir),
        (7, "Social OG/Twitter", verify_rule_7_social_cards),
        (8, "JSON-LD & Forbidden", verify_rule_8_json_ld),
        (9, "Internal Links / 404", verify_rule_9_internal_links),
        (10, "Apple & D&B Entity", verify_rule_10_apple_compliance),
    ]

    for spec in PAGE_SPECS:
        page_id = spec["id"]
        page_name = spec["name"]
        rel_path = spec["rel_path"]
        file_path = os.path.join(public_dir, rel_path)

        page_result = {
            "id": page_id,
            "name": page_name,
            "path": rel_path,
            "exists": os.path.isfile(file_path),
            "rules": {},
            "all_rules_passed": True,
        }

        log(f"\n[{page_id}] {BOLD}{page_name}{RESET} -> {rel_path}")

        if not os.path.isfile(file_path):
            page_result["all_rules_passed"] = False
            results["all_passed"] = False
            diag = f"Page [{page_id}] Missing File: '{rel_path}' does not exist"
            results["diagnostics"].append(diag)
            log(f"  {RED}[FAIL]{RESET} File Not Found: {file_path}")

            for rule_num, rule_title, _ in rules_meta:
                results["total_page_assertions"] += 1
                results["failed_page_assertions"] += 1
                page_result["rules"][f"Rule {rule_num}"] = {
                    "passed": False,
                    "title": rule_title,
                    "message": "File not found",
                }
            results["pages"][page_id] = page_result
            continue

        parser = parse_html_file(file_path)
        page_cache[file_path] = parser

        for rule_num, rule_title, rule_fn in rules_meta:
            results["total_page_assertions"] += 1

            if rule_num in (1, 2, 3):
                ok, msg = rule_fn(parser)
            elif rule_num in (4, 5, 6, 7, 8):
                ok, msg = rule_fn(parser, spec)
            elif rule_num == 9:
                ok, msg = rule_fn(parser, public_dir, rel_path, page_cache)
            elif rule_num == 10:
                ok, msg = rule_fn(parser, public_dir, rel_path)

            page_result["rules"][f"Rule {rule_num}"] = {
                "passed": ok,
                "title": rule_title,
                "message": msg,
            }

            if ok:
                results["passed_page_assertions"] += 1
                if verbose:
                    log(f"    {GREEN}[PASS]{RESET} Rule {rule_num} ({rule_title:<18}): {msg}")
            else:
                results["failed_page_assertions"] += 1
                page_result["all_rules_passed"] = False
                results["all_passed"] = False
                results["diagnostics"].append(f"Page [{page_id}] Rule {rule_num} ({rule_title}): {msg}")
                log(f"    {RED}[FAIL]{RESET} Rule {rule_num} ({rule_title:<18}): {msg}")

        if page_result["all_rules_passed"]:
            log(f"  {GREEN}[ALL 10 RULES PASSED]{RESET}")

        results["pages"][page_id] = page_result

    # 3. AUDIT SUMMARY
    log(f"\n{BOLD}{CYAN}====================================================================={RESET}")
    log(f"{BOLD}AUDIT SUMMARY & QA GATE STATUS:{RESET}")
    log(f"  Total Page Assertions : {results['total_page_assertions']} ({len(PAGE_SPECS)} pages x 10 rules)")
    log(f"  Passed Assertions     : {GREEN}{results['passed_page_assertions']}{RESET}")
    log(f"  Failed Assertions     : {RED if results['failed_page_assertions'] > 0 else GREEN}{results['failed_page_assertions']}{RESET}")

    root_passes = sum(1 for a in results["root_assets"].values() if a["passed"])
    log(f"  Root Assets Verified  : {root_passes}/4")

    if results["all_passed"]:
        log(f"\n{GREEN}{BOLD}>>> RESULT: 100% PASS — ZERO TOLERANCE SEO GATE SATISFIED <<<{RESET}\n")
    else:
        log(f"\n{RED}{BOLD}>>> RESULT: AUDIT FAILED — {len(results['diagnostics'])} ISSUE(S) DETECTED <<<{RESET}")
        log(f"\n{BOLD}Diagnostic Failure Log:{RESET}")
        for idx, diag in enumerate(results["diagnostics"], start=1):
            log(f"  {RED}{idx}.{RESET} {diag}")
        log("")

    return results


# ==============================================================================
# CLI ENTRY POINT
# ==============================================================================
def main():
    default_public = os.path.join(os.path.dirname(os.path.abspath(__file__)), "public")
    parser = argparse.ArgumentParser(
        description="Verify technical SEO compliance and zero-slack rules across Siratie static landing pages."
    )
    parser.add_argument(
        "--public-dir",
        default=default_public,
        help=f"Path to static public directory (default: {default_public})",
    )
    parser.add_argument(
        "--verbose", "-v",
        action="store_true",
        help="Print verbose diagnostics for passed rules",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Output raw audit summary as JSON",
    )

    args = parser.parse_args()

    results = run_seo_audit(args.public_dir, verbose=args.verbose, quiet=args.json)

    if args.json:
        # JSON output mode (clean serialization)
        serializable = {
            "public_dir": results["public_dir"],
            "total_page_assertions": results["total_page_assertions"],
            "passed_page_assertions": results["passed_page_assertions"],
            "failed_page_assertions": results["failed_page_assertions"],
            "all_passed": results["all_passed"],
            "root_assets": results["root_assets"],
            "diagnostics": results["diagnostics"],
        }
        print(json.dumps(serializable, indent=2))

    sys.exit(0 if results["all_passed"] else 1)


if __name__ == "__main__":
    main()
