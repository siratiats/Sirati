#!/usr/bin/env python3
"""
adversarial_stress_suite.py — Independent Adversarial Stress-Testing Harness for Siratie Landing Site

Built by Adversarial Challenger 1 (Milestone M5).
Validates every static HTML file under d:\\Sirati\\public, sitemap.xml, robots.txt, and CNAME
against adversarial edge cases, strict invariants, and structural integrity rules.

Standard Library only: os, sys, re, json, glob, urllib.parse, html.parser, xml.etree.ElementTree.
Exit code: 0 if all tests pass; 1 if any failure occurs.
"""

import os
import sys
import re
import json
import glob
from urllib.parse import urlparse, unquote
from html.parser import HTMLParser
import xml.etree.ElementTree as ET

# Ensure stdout/stderr handle UTF-8 cleanly on Windows
if sys.stdout and hasattr(sys.stdout, "reconfigure"):
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

# Color helpers
GREEN = "\033[92m"
RED = "\033[91m"
YELLOW = "\033[93m"
CYAN = "\033[96m"
BOLD = "\033[1m"
RESET = "\033[0m"

PUBLIC_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "public"))

# HTML5 void elements that do not require closing tags
VOID_TAGS = {
    "area", "base", "br", "col", "embed", "hr", "img", "input",
    "link", "meta", "param", "source", "track", "wbr"
}

# 16 Canonical Page Definitions
EXPECTED_PAGES = [
    {"id": "P1", "rel": "index.html", "url": "https://siratie.com/", "lang": "ar", "dir": "rtl"},
    {"id": "P2", "rel": os.path.join("en", "index.html"), "url": "https://siratie.com/en/", "lang": "en", "dir": "ltr"},
    {"id": "P3", "rel": os.path.join("ats-cv-checker", "index.html"), "url": "https://siratie.com/ats-cv-checker/", "lang": "ar", "dir": "rtl"},
    {"id": "P4", "rel": os.path.join("en", "ats-cv-checker", "index.html"), "url": "https://siratie.com/en/ats-cv-checker/", "lang": "en", "dir": "ltr"},
    {"id": "P5", "rel": os.path.join("cv-builder", "index.html"), "url": "https://siratie.com/cv-builder/", "lang": "ar", "dir": "rtl"},
    {"id": "P6", "rel": os.path.join("en", "cv-builder", "index.html"), "url": "https://siratie.com/en/cv-builder/", "lang": "en", "dir": "ltr"},
    {"id": "P7", "rel": os.path.join("cv-templates", "index.html"), "url": "https://siratie.com/cv-templates/", "lang": "ar", "dir": "rtl"},
    {"id": "P8", "rel": os.path.join("en", "cv-templates", "index.html"), "url": "https://siratie.com/en/cv-templates/", "lang": "en", "dir": "ltr"},
    {"id": "P9", "rel": os.path.join("jobs", "index.html"), "url": "https://siratie.com/jobs/", "lang": "ar", "dir": "rtl"},
    {"id": "P10", "rel": os.path.join("en", "jobs", "index.html"), "url": "https://siratie.com/en/jobs/", "lang": "en", "dir": "ltr"},
    {"id": "P11", "rel": os.path.join("download", "index.html"), "url": "https://siratie.com/download/", "lang": "ar", "dir": "rtl"},
    {"id": "P12", "rel": os.path.join("en", "download", "index.html"), "url": "https://siratie.com/en/download/", "lang": "en", "dir": "ltr"},
    {"id": "P13", "rel": os.path.join("privacy", "index.html"), "url": "https://siratie.com/privacy/", "lang": "ar", "dir": "rtl"},
    {"id": "P14", "rel": os.path.join("en", "privacy", "index.html"), "url": "https://siratie.com/en/privacy/", "lang": "en", "dir": "ltr"},
    {"id": "P15", "rel": os.path.join("terms", "index.html"), "url": "https://siratie.com/terms/", "lang": "ar", "dir": "rtl"},
    {"id": "P16", "rel": os.path.join("en", "terms", "index.html"), "url": "https://siratie.com/en/terms/", "lang": "en", "dir": "ltr"},
]


class FullDOMParser(HTMLParser):
    """Exhaustive DOM parser tracking elements, attributes, IDs, links, images, and tag nesting."""

    def __init__(self, filepath):
        super().__init__(convert_charrefs=True)
        self.filepath = filepath
        self.tag_stack = []
        self.nesting_errors = []
        
        self.ids = set()
        self.duplicate_ids = []
        
        self.doctype_found = False
        self.html_attrs = {}
        self.head_count = 0
        self.body_count = 0
        
        self.meta_tags = []
        self.link_tags = []
        self.a_tags = []
        self.img_tags = []
        self.h1_tags = []
        self.json_ld_blocks = []
        
        self._in_h1 = False
        self._h1_chars = []
        self._in_json_ld = False
        self._json_ld_chars = []
        self._title = None
        self._in_title = False
        self._title_chars = []

    def handle_decl(self, decl):
        if decl.lower().strip() == "doctype html":
            self.doctype_found = True

    def handle_starttag(self, tag, attrs):
        t_low = tag.lower()
        attr_dict = {k.lower(): v for k, v in attrs if k is not None}
        pos = self.getpos()

        # ID tracking
        elem_id = attr_dict.get("id")
        if elem_id:
            if elem_id in self.ids:
                self.duplicate_ids.append((elem_id, pos))
            else:
                self.ids.add(elem_id)

        # Structural tracking
        if t_low == "html":
            self.html_attrs = attr_dict
        elif t_low == "head":
            self.head_count += 1
        elif t_low == "body":
            self.body_count += 1
        elif t_low == "title":
            self._in_title = True
            self._title_chars = []
        elif t_low == "h1":
            self._in_h1 = True
            self._h1_chars = []
        elif t_low == "meta":
            self.meta_tags.append((attr_dict, pos))
        elif t_low == "link":
            self.link_tags.append((attr_dict, pos))
        elif t_low == "a":
            self.a_tags.append((attr_dict, pos))
        elif t_low == "img":
            self.img_tags.append((attr_dict, pos))
        elif t_low == "script":
            stype = attr_dict.get("type", "").lower()
            if stype == "application/ld+json":
                self._in_json_ld = True
                self._json_ld_chars = []

        # Tag nesting stack
        if t_low not in VOID_TAGS:
            self.tag_stack.append((t_low, pos))

    def handle_startendtag(self, tag, attrs):
        # Self-closing tag
        t_low = tag.lower()
        attr_dict = {k.lower(): v for k, v in attrs if k is not None}
        pos = self.getpos()

        elem_id = attr_dict.get("id")
        if elem_id:
            if elem_id in self.ids:
                self.duplicate_ids.append((elem_id, pos))
            else:
                self.ids.add(elem_id)

        if t_low == "meta":
            self.meta_tags.append((attr_dict, pos))
        elif t_low == "link":
            self.link_tags.append((attr_dict, pos))
        elif t_low == "img":
            self.img_tags.append((attr_dict, pos))

    def handle_endtag(self, tag):
        t_low = tag.lower()
        pos = self.getpos()

        if t_low == "title" and self._in_title:
            self._title = "".join(self._title_chars).strip()
            self._in_title = False
        elif t_low == "h1" and self._in_h1:
            self.h1_tags.append("".join(self._h1_chars).strip())
            self._in_h1 = False
        elif t_low == "script" and self._in_json_ld:
            self.json_ld_blocks.append("".join(self._json_ld_chars).strip())
            self._in_json_ld = False

        if t_low in VOID_TAGS:
            self.nesting_errors.append(f"Void tag <{t_low}> closed with explicit end tag at {pos}")
            return

        for i in range(len(self.tag_stack) - 1, -1, -1):
            if self.tag_stack[i][0] == t_low:
                unclosed = self.tag_stack[i+1:]
                if unclosed:
                    self.nesting_errors.append(
                        f"Unclosed tag(s) {[u[0] for u in unclosed]} before </{t_low}> at {pos}"
                    )
                self.tag_stack = self.tag_stack[:i]
                return

        self.nesting_errors.append(f"Unexpected closing tag </{t_low}> at {pos} (not in stack)")

    def handle_data(self, data):
        if self._in_title:
            self._title_chars.append(data)
        if self._in_h1:
            self._h1_chars.append(data)
        if self._in_json_ld:
            self._json_ld_chars.append(data)


class AdversarialTestRunner:
    """Orchestrates adversarial assertions across all public assets."""

    def __init__(self, public_dir=PUBLIC_DIR, strict_images=False):
        self.public_dir = os.path.abspath(public_dir)
        self.strict_images = strict_images
        self.results = []
        self.passed_tests = 0
        self.failed_tests = 0
        self.warnings = []

    def record_test(self, suite, name, success, detail="", warning_only=False):
        status = "PASS" if success else ("WARN" if warning_only else "FAIL")
        if success:
            self.passed_tests += 1
        elif warning_only:
            self.warnings.append((suite, name, detail))
        else:
            self.failed_tests += 1

        self.results.append({
            "suite": suite,
            "name": name,
            "status": status,
            "detail": detail
        })

    def run_all(self):
        print(f"\n{BOLD}{CYAN}=== ADVERSARIAL STRESS TEST SUITE — SIRATIE STATIC PLATFORM ==={RESET}")
        print(f"Target Public Directory: {self.public_dir}\n")

        # Discover all HTML files
        html_files = sorted(glob.glob(os.path.join(self.public_dir, "**", "*.html"), recursive=True))
        print(f"Discovered {len(html_files)} HTML files for deep stress-testing.\n")

        # Parse all documents into DOM
        parsed_pages = {}
        for hf in html_files:
            rel = os.path.relpath(hf, self.public_dir)
            p = FullDOMParser(hf)
            try:
                with open(hf, "r", encoding="utf-8") as f:
                    p.feed(f.read())
            except Exception as e:
                self.record_test("HTML Parsing", f"{rel} Parse", False, f"Exception reading/parsing: {e}")
            parsed_pages[hf] = p

        # 1. Encoding & Charset Suite
        self._test_encoding_and_charset(html_files)

        # 2. DOM Structure & Tag Nesting Suite
        self._test_dom_structure(parsed_pages)

        # 3. Trailing Slashes on Internal Links Suite
        self._test_internal_links_trailing_slashes(parsed_pages)

        # 4. Fragment & Anchor Target Validation Suite
        self._test_anchor_targets(parsed_pages)

        # 5. Image Tags Strict Audit Suite
        self._test_image_tags(parsed_pages)

        # 6. XML Sitemap Deep Validation Suite
        self._test_sitemap_xml()

        # 7. Robots.txt & CNAME Apex Domain Suite
        self._test_robots_and_cname()

        # 8. 404 Error Page Suite
        self._test_404_error_page(parsed_pages)

        # 9. Schema.org JSON-LD Structured Data Suite
        self._test_json_ld_schemas(parsed_pages)

        # 10. Canonical & Hreflang Symmetrical Triplet Suite
        self._test_canonical_and_hreflang(parsed_pages)

        self._print_summary()
        return self.failed_tests == 0

    # --------------------------------------------------------------------------
    # Suite 1: Encoding & Charset Integrity
    # --------------------------------------------------------------------------
    def _test_encoding_and_charset(self, html_files):
        suite = "Suite 1: Encoding & Charset"
        for hf in html_files:
            rel = os.path.relpath(hf, self.public_dir)
            
            # Read raw bytes
            with open(hf, "rb") as f:
                raw_bytes = f.read()

            # Check valid UTF-8
            try:
                decoded = raw_bytes.decode("utf-8")
                self.record_test(suite, f"{rel} UTF-8 Decodable", True, "Decodes strictly as UTF-8")
            except UnicodeDecodeError as e:
                self.record_test(suite, f"{rel} UTF-8 Decodable", False, f"UTF-8 decode error: {e}")
                continue

            # Check for UTF-8 BOM
            has_bom = raw_bytes.startswith(b"\xef\xbb\xbf")
            self.record_test(suite, f"{rel} No UTF-8 BOM", not has_bom, "UTF-8 BOM must be absent")

            # Check meta charset within first 1024 bytes (HTML5 standard requirement)
            first_1k = raw_bytes[:1024].decode("utf-8", errors="ignore").lower()
            has_early_charset = bool(re.search(r'<meta\s+charset=["\']?utf-8["\']?', first_1k))
            self.record_test(
                suite,
                f"{rel} Early <meta charset>",
                has_early_charset,
                "Declared <meta charset=\"UTF-8\"> within first 1024 bytes"
            )

            # Check for mojibake corrupt character signatures
            mojibake_patterns = [r"Ã¢", r"Ã©", r"â€™", r"â€œ", r"â€", r"\ufffd"]
            found_mojibake = []
            for pat in mojibake_patterns:
                if re.search(pat, decoded):
                    found_mojibake.append(pat)
            self.record_test(
                suite,
                f"{rel} No Mojibake Corruption",
                len(found_mojibake) == 0,
                f"Found mojibake patterns: {found_mojibake}" if found_mojibake else "No mojibake detected"
            )

    # --------------------------------------------------------------------------
    # Suite 2: DOM Structure & Tag Nesting
    # --------------------------------------------------------------------------
    def _test_dom_structure(self, parsed_pages):
        suite = "Suite 2: DOM Structure & Nesting"
        for hf, p in parsed_pages.items():
            rel = os.path.relpath(hf, self.public_dir)

            # DOCTYPE
            self.record_test(suite, f"{rel} DOCTYPE html", p.doctype_found, "Must start with <!DOCTYPE html>")

            # Exactly one head and one body
            self.record_test(suite, f"{rel} Single <head> & <body>", p.head_count == 1 and p.body_count == 1,
                             f"head={p.head_count}, body={p.body_count}")

            # HTML lang and dir attributes
            lang = p.html_attrs.get("lang", "")
            dir_attr = p.html_attrs.get("dir", "")
            valid_lang_dir = (lang in ("ar", "en")) and (dir_attr in ("rtl", "ltr"))
            if "en" in rel.lower().split(os.sep):
                expected_lang, expected_dir = "en", "ltr"
            elif rel == "404.html":
                expected_lang, expected_dir = "ar", "rtl"
            else:
                expected_lang, expected_dir = "ar", "rtl"

            match_lang_dir = (lang == expected_lang and dir_attr == expected_dir)
            self.record_test(
                suite,
                f"{rel} <html> lang/dir alignment",
                match_lang_dir,
                f"expected ({expected_lang}, {expected_dir}), got ({lang}, {dir_attr})"
            )

            # Duplicate IDs
            no_dup_ids = len(p.duplicate_ids) == 0
            self.record_test(
                suite,
                f"{rel} Unique Element IDs",
                no_dup_ids,
                f"Duplicate IDs found: {p.duplicate_ids}" if not no_dup_ids else "All IDs unique"
            )

            # Balanced tag nesting
            no_nesting_errs = len(p.nesting_errors) == 0 and len(p.tag_stack) == 0
            err_detail = f"Errors: {p.nesting_errors}; Unclosed at EOF: {[t[0] for t in p.tag_stack]}" if not no_nesting_errs else "Clean balanced tags"
            self.record_test(suite, f"{rel} Balanced Tag Nesting", no_nesting_errs, err_detail)

    # --------------------------------------------------------------------------
    # Suite 3: Trailing Slashes on Internal Links
    # --------------------------------------------------------------------------
    def _test_internal_links_trailing_slashes(self, parsed_pages):
        suite = "Suite 3: Internal Links Trailing Slashes"
        for hf, p in parsed_pages.items():
            rel = os.path.relpath(hf, self.public_dir)
            errors = []

            for attrs, pos in p.a_tags:
                href = attrs.get("href", "").strip()
                if not href or href.startswith(("mailto:", "tel:", "javascript:", "sms:", "data:", "[PLACEHOLDER:")):
                    continue

                parsed = urlparse(href)
                # Check if internal
                if parsed.scheme in ("http", "https"):
                    if parsed.netloc.lower() not in ("siratie.com", "www.siratie.com"):
                        continue  # external link
                    path = parsed.path
                elif parsed.scheme == "":
                    path = parsed.path
                else:
                    continue

                # Ignore pure fragments on same page
                if not path and parsed.fragment:
                    continue

                if path:
                    # Check if file has extension
                    clean = path.rstrip("/")
                    base = os.path.basename(clean)
                    has_ext = "." in base

                    if not has_ext and not path.endswith("/"):
                        errors.append(f"Line {pos[0]}: link '{href}' missing trailing slash on directory path '{path}'")

                    # Check physical resolution
                    if path.startswith("/"):
                        rel_path = path.lstrip("/")
                        if not rel_path:
                            target_file = os.path.join(self.public_dir, "index.html")
                        elif path.endswith("/"):
                            target_file = os.path.join(self.public_dir, rel_path, "index.html")
                        else:
                            target_file = os.path.join(self.public_dir, rel_path)
                    else:
                        cur_dir = os.path.dirname(hf)
                        if path.endswith("/"):
                            target_file = os.path.join(cur_dir, path, "index.html")
                        else:
                            target_file = os.path.join(cur_dir, path)

                    target_norm = os.path.normpath(target_file)
                    if not os.path.exists(target_norm):
                        errors.append(f"Line {pos[0]}: link '{href}' -> target does not exist: '{target_norm}'")

            self.record_test(
                suite,
                f"{rel} Internal Links Trailing Slashes & Resolution",
                len(errors) == 0,
                "; ".join(errors[:3]) if errors else f"All {len(p.a_tags)} links verified"
            )

    # --------------------------------------------------------------------------
    # Suite 4: Fragment & Anchor Target Validation
    # --------------------------------------------------------------------------
    def _test_anchor_targets(self, parsed_pages):
        suite = "Suite 4: Fragment & Anchor Targets"
        for hf, p in parsed_pages.items():
            rel = os.path.relpath(hf, self.public_dir)
            errors = []

            for attrs, pos in p.a_tags:
                href = attrs.get("href", "").strip()
                if "#" not in href or href.startswith(("mailto:", "tel:", "javascript:")):
                    continue

                parsed = urlparse(href)
                fragment = parsed.fragment
                path = parsed.path

                if not fragment:
                    errors.append(f"Line {pos[0]}: Empty fragment anchor in '{href}'")
                    continue

                if not path:
                    # Same page anchor
                    if fragment not in p.ids:
                        errors.append(f"Line {pos[0]}: Same-page anchor #{fragment} not found in {rel}")
                else:
                    # Target page anchor
                    if path == "/":
                        target_file = os.path.join(self.public_dir, "index.html")
                    elif path == "/en/":
                        target_file = os.path.join(self.public_dir, "en", "index.html")
                    elif path.startswith("/"):
                        target_file = os.path.join(self.public_dir, path.lstrip("/"), "index.html")
                    else:
                        target_file = os.path.join(os.path.dirname(hf), path, "index.html")

                    target_norm = os.path.normpath(target_file)
                    target_parser = parsed_pages.get(target_norm)
                    if not target_parser:
                        errors.append(f"Line {pos[0]}: Anchor target file '{target_norm}' not in parsed pages")
                    elif fragment not in target_parser.ids:
                        errors.append(f"Line {pos[0]}: Target anchor #{fragment} not found in '{os.path.relpath(target_norm, self.public_dir)}'")

            self.record_test(
                suite,
                f"{rel} Anchor Fragments Integrity",
                len(errors) == 0,
                "; ".join(errors[:3]) if errors else "All anchor fragments resolved"
            )

    # --------------------------------------------------------------------------
    # Suite 5: Image Tags Strict Audit
    # --------------------------------------------------------------------------
    def _test_image_tags(self, parsed_pages):
        suite = "Suite 5: Image Tags Audit"
        for hf, p in parsed_pages.items():
            rel = os.path.relpath(hf, self.public_dir)
            if not p.img_tags:
                self.record_test(suite, f"{rel} Images Checked", True, "No <img> tags on this page")
                continue

            errors = []
            warnings = []
            for attrs, pos in p.img_tags:
                src = attrs.get("src", "").strip()
                width = attrs.get("width", "").strip()
                height = attrs.get("height", "").strip()
                loading = attrs.get("loading", "").strip().lower()
                alt = attrs.get("alt")

                # 1. src presence and disk existence
                if not src:
                    errors.append(f"Line {pos[0]}: <img> tag missing src attribute")
                else:
                    if src.startswith("/"):
                        img_path = os.path.join(self.public_dir, src.lstrip("/"))
                    else:
                        img_path = os.path.join(os.path.dirname(hf), src)
                    if not os.path.exists(os.path.normpath(img_path)):
                        errors.append(f"Line {pos[0]}: Image file '{src}' does not exist on disk")

                # 2. explicit width and height
                if not width or not width.isdigit() or int(width) <= 0:
                    errors.append(f"Line {pos[0]}: Image '{src}' missing valid explicit width ('{width}')")
                if not height or not height.isdigit() or int(height) <= 0:
                    errors.append(f"Line {pos[0]}: Image '{src}' missing valid explicit height ('{height}')")

                # 3. descriptive alt
                if alt is None:
                    errors.append(f"Line {pos[0]}: Image '{src}' missing alt attribute")
                elif len(alt.strip()) < 5:
                    errors.append(f"Line {pos[0]}: Image '{src}' alt is too short or empty ('{alt}')")
                else:
                    # Language check
                    if "en" in rel.lower().split(os.sep):
                        has_latin = bool(re.search(r'[A-Za-z]', alt))
                        if not has_latin:
                            warnings.append(f"Line {pos[0]}: Image '{src}' alt on English page contains no Latin characters: '{alt}'")
                    else:
                        has_arabic = bool(re.search(r'[\u0600-\u06FF]', alt))
                        if not has_arabic:
                            warnings.append(f"Line {pos[0]}: Image '{src}' alt on Arabic page contains no Arabic characters: '{alt}'")

                # 4. loading attribute
                if not loading:
                    msg = f"Line {pos[0]}: Image '{src}' lacks explicit loading attribute (expected 'lazy' or 'eager')"
                    if self.strict_images:
                        errors.append(msg)
                    else:
                        warnings.append(msg)
                elif loading not in ("lazy", "eager"):
                    errors.append(f"Line {pos[0]}: Image '{src}' invalid loading attribute value '{loading}'")

            # Report image results
            self.record_test(
                suite,
                f"{rel} Image Attributes (src, w, h, alt, loading)",
                len(errors) == 0,
                "; ".join(errors[:3]) if errors else f"{len(p.img_tags)} image(s) verified"
            )
            if warnings:
                for w in warnings:
                    self.record_test(suite, f"{rel} Image Advisory", False, w, warning_only=True)

    # --------------------------------------------------------------------------
    # Suite 6: XML Sitemap Deep Validation
    # --------------------------------------------------------------------------
    def _test_sitemap_xml(self):
        suite = "Suite 6: XML Sitemap Deep Validation"
        sitemap_path = os.path.join(self.public_dir, "sitemap.xml")

        if not os.path.isfile(sitemap_path):
            self.record_test(suite, "sitemap.xml Exists", False, "File does not exist")
            return

        self.record_test(suite, "sitemap.xml Exists", True, "File exists")

        # Parse with XML parser
        try:
            tree = ET.parse(sitemap_path)
            root = tree.getroot()
            self.record_test(suite, "sitemap.xml Valid XML", True, "Parsed without XML syntax errors")
        except Exception as e:
            self.record_test(suite, "sitemap.xml Valid XML", False, f"XML syntax error: {e}")
            return

        ns = {
            "s": "http://www.sitemaps.org/schemas/sitemap/0.9",
            "xhtml": "http://www.w3.org/1999/xhtml"
        }

        urls = root.findall("s:url", ns)
        self.record_test(suite, "sitemap.xml 16 URLs Count", len(urls) == 16, f"Found {len(urls)} <url> entries (expected 16)")

        expected_canonicals = {spec["url"] for spec in EXPECTED_PAGES}
        found_canonicals = set()
        xhtml_links_count = 0
        sitemap_errors = []

        date_regex = re.compile(r"^\d{4}-\d{2}-\d{2}$")

        for idx, u in enumerate(urls, 1):
            loc_elem = u.find("s:loc", ns)
            if loc_elem is None or not (loc_elem.text or "").strip():
                sitemap_errors.append(f"URL entry #{idx} missing <loc>")
                continue

            loc = loc_elem.text.strip()
            found_canonicals.add(loc)

            # Trailing slash
            if not loc.endswith("/"):
                sitemap_errors.append(f"URL '{loc}' missing trailing slash")

            # Check corresponding file on disk
            rel_path = loc.replace("https://siratie.com/", "")
            target_file = os.path.join(self.public_dir, rel_path, "index.html") if rel_path else os.path.join(self.public_dir, "index.html")
            if not os.path.isfile(os.path.normpath(target_file)):
                sitemap_errors.append(f"URL '{loc}' does not map to physical file: {target_file}")

            # Optional lastmod
            lastmod_elem = u.find("s:lastmod", ns)
            if lastmod_elem is not None and lastmod_elem.text:
                lm = lastmod_elem.text.strip()
                if not date_regex.match(lm):
                    sitemap_errors.append(f"URL '{loc}' invalid lastmod date format: '{lm}'")

            # xhtml:link alternates
            links = u.findall("xhtml:link", ns)
            xhtml_links_count += len(links)

            langs_map = {}
            for l in links:
                rel_attr = l.get("rel", "").lower()
                hl = l.get("hreflang", "").lower()
                href = l.get("href", "").strip()

                if rel_attr != "alternate":
                    sitemap_errors.append(f"URL '{loc}' xhtml:link rel is '{rel_attr}' (expected 'alternate')")
                if not href.endswith("/"):
                    sitemap_errors.append(f"URL '{loc}' alternate href '{href}' missing trailing slash")
                langs_map[hl] = href

            # Verify ar, en, x-default present
            for req_lang in ("ar", "en", "x-default"):
                if req_lang not in langs_map:
                    sitemap_errors.append(f"URL '{loc}' missing xhtml:link hreflang='{req_lang}'")

        self.record_test(
            suite,
            "sitemap.xml Canonical Coverage",
            found_canonicals == expected_canonicals,
            f"Missing canonicals: {expected_canonicals - found_canonicals}" if expected_canonicals != found_canonicals else "All 16 canonicals present"
        )

        self.record_test(
            suite,
            "sitemap.xml 48 Alternate Links Count",
            xhtml_links_count == 48,
            f"Found {xhtml_links_count} alternate links (expected 48)"
        )

        self.record_test(
            suite,
            "sitemap.xml Entry Specifications",
            len(sitemap_errors) == 0,
            "; ".join(sitemap_errors[:3]) if sitemap_errors else "All URL entries valid"
        )

    # --------------------------------------------------------------------------
    # Suite 7: Robots.txt & CNAME Apex Domain
    # --------------------------------------------------------------------------
    def _test_robots_and_cname(self):
        suite = "Suite 7: Robots.txt & CNAME"

        # Robots.txt
        robots_path = os.path.join(self.public_dir, "robots.txt")
        if not os.path.isfile(robots_path):
            self.record_test(suite, "robots.txt Exists", False, "File missing")
        else:
            with open(robots_path, "r", encoding="utf-8") as f:
                content = f.read()

            lines = [line.strip() for line in content.splitlines() if line.strip() and not line.strip().startswith("#")]
            has_ua = any(re.match(r"^user-agent:\s*\*", l, re.IGNORECASE) for l in lines)
            has_allow = any(re.match(r"^allow:\s*/$", l, re.IGNORECASE) for l in lines)
            has_sitemap = any(re.match(r"^sitemap:\s*https://siratie\.com/sitemap\.xml$", l, re.IGNORECASE) for l in lines)

            self.record_test(suite, "robots.txt User-agent: *", has_ua, "Must declare User-agent: *")
            self.record_test(suite, "robots.txt Allow: /", has_allow, "Must declare Allow: /")
            self.record_test(suite, "robots.txt Sitemap Directive", has_sitemap, "Must declare Sitemap: https://siratie.com/sitemap.xml")

        # CNAME
        cname_path = os.path.join(self.public_dir, "CNAME")
        if not os.path.isfile(cname_path):
            self.record_test(suite, "CNAME Exists", False, "File missing")
        else:
            with open(cname_path, "r", encoding="utf-8") as f:
                cname_raw = f.read()

            cname_clean = cname_raw.strip()
            is_apex = (cname_clean == "siratie.com")
            no_protocol = not ("http://" in cname_raw or "https://" in cname_raw)
            no_path = "/" not in cname_clean
            no_www = not cname_clean.startswith("www.")

            self.record_test(
                suite,
                "CNAME Apex Domain Syntax",
                is_apex and no_protocol and no_path and no_www,
                f"Content is '{cname_clean}' (exact apex: {is_apex})"
            )

    # --------------------------------------------------------------------------
    # Suite 8: 404 Custom Error Page
    # --------------------------------------------------------------------------
    def _test_404_error_page(self, parsed_pages):
        suite = "Suite 8: 404 Error Page Integrity"
        p404_path = os.path.normpath(os.path.join(self.public_dir, "404.html"))
        p = parsed_pages.get(p404_path)

        if not p:
            self.record_test(suite, "404.html Exists", False, "File missing")
            return

        self.record_test(suite, "404.html Exists", True, "File exists")

        # Noindex meta tag
        has_noindex = False
        for attrs, pos in p.meta_tags:
            name = attrs.get("name", "").lower()
            content = attrs.get("content", "").lower()
            if name == "robots" and "noindex" in content:
                has_noindex = True
                break

        self.record_test(suite, "404.html robots noindex", has_noindex, "Must contain <meta name=\"robots\" content=\"noindex, ...\">")

        # Homepage return link
        has_home_link = False
        for attrs, pos in p.a_tags:
            href = attrs.get("href", "").strip()
            if href in ("/", "/en/", "https://siratie.com/"):
                has_home_link = True
                break

        self.record_test(suite, "404.html Homepage Return Link", has_home_link, "Must contain link back to homepage")

    # --------------------------------------------------------------------------
    # Suite 9: Schema.org JSON-LD Structured Data
    # --------------------------------------------------------------------------
    def _test_json_ld_schemas(self, parsed_pages):
        suite = "Suite 9: JSON-LD Structured Data"
        forbidden_keywords = ["jobposting", "howto", "aggregaterating"]

        for spec in EXPECTED_PAGES:
            hf = os.path.normpath(os.path.join(self.public_dir, spec["rel"]))
            p = parsed_pages.get(hf)
            if not p:
                continue

            rel = spec["rel"]
            if not p.json_ld_blocks:
                self.record_test(suite, f"{rel} JSON-LD Block Present", False, "Missing JSON-LD structured data")
                continue

            parsed_data = []
            parse_errors = []
            for idx, block in enumerate(p.json_ld_blocks, 1):
                try:
                    data = json.loads(block)
                    parsed_data.append(data)
                except Exception as e:
                    parse_errors.append(f"Block #{idx} decode error: {e}")

            if parse_errors:
                self.record_test(suite, f"{rel} Valid JSON Syntax", False, "; ".join(parse_errors))
                continue
            else:
                self.record_test(suite, f"{rel} Valid JSON Syntax", True, "All JSON-LD blocks valid JSON")

            # Check forbidden schemas
            all_types = set()
            def collect_types(obj):
                if isinstance(obj, dict):
                    for k, v in obj.items():
                        if k in ("@type", "type"):
                            if isinstance(v, str):
                                all_types.add(v)
                            elif isinstance(v, list):
                                for item in v:
                                    if isinstance(item, str):
                                        all_types.add(item)
                        collect_types(v)
                elif isinstance(obj, list):
                    for item in obj:
                        collect_types(item)

            for d in parsed_data:
                collect_types(d)

            detected_forbidden = [t for t in all_types if any(fb in t.lower() for fb in forbidden_keywords)]
            self.record_test(
                suite,
                f"{rel} Zero Forbidden Schemas",
                len(detected_forbidden) == 0,
                f"Detected forbidden schemas: {detected_forbidden}" if detected_forbidden else "No black-hat schemas"
            )

    # --------------------------------------------------------------------------
    # Suite 10: Canonical & Hreflang Symmetrical Triplet
    # --------------------------------------------------------------------------
    def _test_canonical_and_hreflang(self, parsed_pages):
        suite = "Suite 10: Canonical & Hreflang Symmetrical Triplet"

        for spec in EXPECTED_PAGES:
            hf = os.path.normpath(os.path.join(self.public_dir, spec["rel"]))
            p = parsed_pages.get(hf)
            if not p:
                continue

            rel = spec["rel"]
            expected_canonical = spec["url"]

            # Canonical tag check
            canonical_hrefs = [
                attrs.get("href", "").strip()
                for attrs, pos in p.link_tags
                if "canonical" in attrs.get("rel", "").lower().split()
            ]

            is_valid_canonical = (len(canonical_hrefs) == 1 and canonical_hrefs[0] == expected_canonical)
            self.record_test(
                suite,
                f"{rel} Self-Referencing Canonical",
                is_valid_canonical,
                f"expected '{expected_canonical}', found {canonical_hrefs}"
            )

            # Hreflang triplet check
            hreflangs = {}
            for attrs, pos in p.link_tags:
                rel_attr = attrs.get("rel", "").lower().split()
                if "alternate" in rel_attr and "hreflang" in attrs:
                    hl = attrs.get("hreflang", "").lower()
                    href = attrs.get("href", "").strip()
                    hreflangs[hl] = href

            has_ar = "ar" in hreflangs and hreflangs["ar"].endswith("/")
            has_en = "en" in hreflangs and hreflangs["en"].endswith("/")
            has_xdef = "x-default" in hreflangs and hreflangs["x-default"].endswith("/")
            all_three = has_ar and has_en and has_xdef

            # Check reciprocal matching
            is_reciprocal = False
            if all_three:
                # x-default must match Arabic version
                is_reciprocal = (hreflangs["x-default"] == hreflangs["ar"])

            self.record_test(
                suite,
                f"{rel} Symmetrical Hreflang Triplet (ar, en, x-default)",
                all_three and is_reciprocal,
                f"ar={hreflangs.get('ar')}, en={hreflangs.get('en')}, x-default={hreflangs.get('x-default')}"
            )

    # --------------------------------------------------------------------------
    # Summary & Output
    # --------------------------------------------------------------------------
    def _print_summary(self):
        print(f"\n{BOLD}====================================================================={RESET}")
        print(f"{BOLD}ADVERSARIAL STRESS TEST RESULTS SUMMARY:{RESET}")
        print(f"  Total Assertions Run : {self.passed_tests + self.failed_tests}")
        print(f"  Passed Assertions    : {GREEN}{self.passed_tests}{RESET}")
        print(f"  Failed Assertions    : {RED if self.failed_tests > 0 else GREEN}{self.failed_tests}{RESET}")
        print(f"  Advisory Warnings    : {YELLOW}{len(self.warnings)}{RESET}")

        if self.warnings:
            print(f"\n{BOLD}{YELLOW}Advisory Warnings:{RESET}")
            for s, n, d in self.warnings:
                print(f"  [{YELLOW}WARN{RESET}] [{s}] {n}: {d}")

        if self.failed_tests > 0:
            print(f"\n{BOLD}{RED}FAILED ASSERTIONS:{RESET}")
            for r in self.results:
                if r["status"] == "FAIL":
                    print(f"  [{RED}FAIL{RESET}] [{r['suite']}] {r['name']}: {r['detail']}")
            print(f"\n{BOLD}{RED}>>> VERDICT: REQUEST_CHANGES (Failures detected) <<<{RESET}\n")
        else:
            print(f"\n{BOLD}{GREEN}>>> VERDICT: APPROVE (100% Pass across all adversarial tests) <<<{RESET}\n")
        print(f"{BOLD}====================================================================={RESET}\n")


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Adversarial Stress Test Suite for Siratie Landing Site")
    parser.add_argument("--public-dir", default=PUBLIC_DIR, help="Path to public directory")
    parser.add_argument("--strict-images", action="store_true", help="Fail if any image lacks explicit loading attribute")
    args = parser.parse_args()

    runner = AdversarialTestRunner(public_dir=args.public_dir, strict_images=args.strict_images)
    success = runner.run_all()
    sys.exit(0 if success else 1)
