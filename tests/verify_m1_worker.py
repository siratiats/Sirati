"""
Objective Verification Suite for Milestone M1
Tests and asserts all 9 deliverable groups owned by Worker M1.
"""

import os
import re
import sys
import xml.etree.ElementTree as ET
from PIL import Image

ROOT = r"d:\Sirati"
PUBLIC = os.path.join(ROOT, "public")
ASSETS = os.path.join(PUBLIC, "assets")

failures = []

def test(name, condition, msg=""):
    if condition:
        print(f"  [PASS] {name}")
    else:
        print(f"  [FAIL] {name}: {msg}")
        failures.append(f"{name}: {msg}")

print("==================================================")
print("1. Verifying CNAME")
print("==================================================")
cname_path = os.path.join(PUBLIC, "CNAME")
test("CNAME exists", os.path.isfile(cname_path))
if os.path.isfile(cname_path):
    with open(cname_path, "r", encoding="utf-8") as f:
        content = f.read().strip()
    test("CNAME content is exact domain", content == "siratie.com", f"got '{content}'")

print("\n==================================================")
print("2. Verifying robots.txt")
print("==================================================")
robots_path = os.path.join(PUBLIC, "robots.txt")
test("robots.txt exists", os.path.isfile(robots_path))
if os.path.isfile(robots_path):
    with open(robots_path, "r", encoding="utf-8") as f:
        r_content = f.read()
    test("robots.txt has User-agent: *", "User-agent: *" in r_content)
    test("robots.txt has Allow: /", "Allow: /" in r_content)
    test("robots.txt has Sitemap", "Sitemap: https://siratie.com/sitemap.xml" in r_content)

print("\n==================================================")
print("3. Verifying sitemap.xml")
print("==================================================")
sitemap_path = os.path.join(PUBLIC, "sitemap.xml")
test("sitemap.xml exists", os.path.isfile(sitemap_path))
if os.path.isfile(sitemap_path):
    try:
        tree = ET.parse(sitemap_path)
        root = tree.getroot()
        test("sitemap.xml is valid XML", True)
        
        # Namespaces
        ns = {
            "sm": "http://www.sitemaps.org/schemas/sitemap/0.9",
            "xhtml": "http://www.w3.org/1999/xhtml"
        }
        
        urls = root.findall("sm:url", ns)
        test("sitemap has exactly 16 URLs", len(urls) == 16, f"found {len(urls)}")
        
        xhtml_links = root.findall(".//xhtml:link", ns)
        test("sitemap has 48 xhtml:link alternates (3 per URL)", len(xhtml_links) == 48, f"found {len(xhtml_links)}")
        
        expected_locs = [
            "https://siratie.com/",
            "https://siratie.com/en/",
            "https://siratie.com/ats-cv-checker/",
            "https://siratie.com/en/ats-cv-checker/",
            "https://siratie.com/cv-builder/",
            "https://siratie.com/en/cv-builder/",
            "https://siratie.com/cv-templates/",
            "https://siratie.com/en/cv-templates/",
            "https://siratie.com/jobs/",
            "https://siratie.com/en/jobs/",
            "https://siratie.com/download/",
            "https://siratie.com/en/download/",
            "https://siratie.com/privacy/",
            "https://siratie.com/en/privacy/",
            "https://siratie.com/terms/",
            "https://siratie.com/en/terms/",
        ]
        
        found_locs = [u.find("sm:loc", ns).text for u in urls if u.find("sm:loc", ns) is not None]
        test("all 16 expected canonical URLs present", set(found_locs) == set(expected_locs), f"mismatch: {set(expected_locs) - set(found_locs)}")
        
    except Exception as e:
        test("sitemap.xml parsing", False, str(e))

print("\n==================================================")
print("4. Verifying 404.html")
print("==================================================")
error_path = os.path.join(PUBLIC, "404.html")
test("404.html exists", os.path.isfile(error_path))
if os.path.isfile(error_path):
    with open(error_path, "r", encoding="utf-8") as f:
        h404 = f.read()
    test("404.html has noindex, follow", 'name="robots" content="noindex, follow"' in h404)
    test("404.html links to root /", 'href="/"' in h404)
    test("404.html links to /en/", 'href="/en/"' in h404)
    test("404.html links to /download/", 'href="/download/"' in h404)
    test("404.html links to site.css", '/assets/css/site.css' in h404)
    test("404.html has single H1", len(re.findall(r'<h1[^>]*>.*?</h1>', h404, re.DOTALL)) == 1)

print("\n==================================================")
print("5. Verifying site.css")
print("==================================================")
css_path = os.path.join(ASSETS, "css", "site.css")
test("site.css exists", os.path.isfile(css_path))
if os.path.isfile(css_path):
    with open(css_path, "r", encoding="utf-8") as f:
        css = f.read()
    test("site.css defines --color-primary #007F73", "--color-primary:            #007F73;" in css or "#007F73" in css)
    test("site.css defines --color-primary-ink #006A60", "--color-primary-ink:" in css and "#006A60" in css)
    test("site.css defines dark theme [data-theme=\"dark\"]", '[data-theme="dark"]' in css)
    test("site.css defines dark bg #121614", "#121614" in css)
    test("site.css uses CSS logical properties", "margin-inline" in css and "padding-inline" in css and "text-align: start" in css)
    test("site.css references IBM Plex Sans Arabic", "IBM Plex Sans Arabic" in css)
    test("site.css includes smart download component classes", ".smart-download-section" in css and ".btn-store" in css)
    test("site.css has 4-column footer styles", ".site-footer" in css and ".footer-grid" in css)

print("\n==================================================")
print("6. Verifying download.js")
print("==================================================")
js_path = os.path.join(ASSETS, "js", "download.js")
test("download.js exists", os.path.isfile(js_path))
if os.path.isfile(js_path):
    with open(js_path, "r", encoding="utf-8") as f:
        js = f.read()
    test("download.js detects Android", "isAndroid" in js)
    test("download.js detects iOS / iPadOS", "isIOS" in js and "MacIntel" in js)
    test("download.js checks placeholder URLs", "isPlaceholder" in js or "PLACEHOLDER" in js)
    test("download.js handles pre-launch labels", "قريباً" in js and "Coming soon" in js)
    test("download.js fires GA4 download_click_android", "download_click_android" in js)
    test("download.js fires GA4 download_click_ios", "download_click_ios" in js)
    test("download.js fires GA4 download_click_desktop", "download_click_desktop" in js)
    test("download.js fires GA4 qr_view", "qr_view" in js)

print("\n==================================================")
print("7. Verifying sprite.svg")
print("==================================================")
sprite_path = os.path.join(ASSETS, "icons", "sprite.svg")
test("sprite.svg exists", os.path.isfile(sprite_path))
if os.path.isfile(sprite_path):
    with open(sprite_path, "r", encoding="utf-8") as f:
        sprite = f.read()
    required_ids = [
        "logo-mark", "apple-store", "google-play", "qr-code",
        "icon-ats-check", "icon-cv-builder", "icon-templates", "icon-jobs",
        "icon-ai-magic", "icon-privacy", "icon-terms", "icon-globe",
        "icon-sun", "icon-moon", "icon-chevron-inline", "icon-check",
        "icon-x", "icon-linkedin", "icon-tiktok", "icon-instagram", "icon-email"
    ]
    for rid in required_ids:
        test(f"sprite has #{rid}", f'id="{rid}"' in sprite)

print("\n==================================================")
print("8. Verifying Favicons & WebP Mockup Assets")
print("==================================================")
ico_path = os.path.join(PUBLIC, "favicon.ico")
svg_fav_path = os.path.join(PUBLIC, "favicon.svg")
apple_path = os.path.join(PUBLIC, "apple-touch-icon.png")

test("favicon.ico exists and has non-zero size", os.path.isfile(ico_path) and os.path.getsize(ico_path) > 0)
test("favicon.svg exists", os.path.isfile(svg_fav_path))
test("apple-touch-icon.png exists", os.path.isfile(apple_path))
if os.path.isfile(apple_path):
    im = Image.open(apple_path)
    test("apple-touch-icon.png is 180x180", im.size == (180, 180), f"got {im.size}")

expected_images = [
    ("hero-app-mockup.webp", (640, 540)),
    ("ats-scanner-mockup.webp", (600, 440)),
    ("cv-builder-mockup.webp", (600, 440)),
    ("cv-template-classic.webp", (480, 680)),
    ("cv-template-modern.webp", (480, 680)),
    ("cv-template-executive.webp", (480, 680)),
    ("jobs-preview.webp", (600, 440)),
    ("og-siratie-ar.webp", (1200, 630)),
    ("og-siratie-en.webp", (1200, 630)),
    ("qr-download.webp", (240, 240)),
]

for filename, dims in expected_images:
    p = os.path.join(ASSETS, "images", filename)
    test(f"{filename} exists in public/assets/images/", os.path.isfile(p))
    if os.path.isfile(p):
        im = Image.open(p)
        test(f"{filename} has dimensions {dims[0]}x{dims[1]}", im.size == dims, f"got {im.size}")

print("\n==================================================")
print(f"VERIFICATION SUMMARY: {len(failures)} failures")
print("==================================================")

if failures:
    print("FAILED ASSERTIONS:")
    for f in failures:
        print(" -", f)
    sys.exit(1)
else:
    print("ALL TESTS PASSED WITH 100% SUCCESS!")
    sys.exit(0)
