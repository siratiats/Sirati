#!/usr/bin/env python3
"""
fix_arabic_typography_and_bidi.py
Systematically fixes:
1. Google Fonts preconnect & link in <head> for all HTML files
2. BiDi parentheses flip around acronyms: (ATS) -> <bdi dir="ltr">(ATS)</bdi>, (BiDi), (PDF), etc.
3. Phone numbers, CR numbers, store names in Arabic text isolated with dir="ltr" / <bdi>
4. Footer details across all Arabic pages (Digital Pass Establishment, CR, phone, email, etc.)
5. Breadcrumb separators
"""

import os
import re

PUBLIC_DIR = r"d:\Sirati\public"

FONT_LINKS = """  <!-- Preconnect & Typography Fonts (IBM Plex Sans Arabic & Plus Jakarta Sans) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
"""

def add_font_links(content):
    if "fonts.googleapis.com" in content:
        return content
    # Find position before site.css stylesheet
    match = re.search(r'([ \t]*<link rel="stylesheet" [^>]*site\.css[^>]*>)', content)
    if match:
        target = match.group(1)
        return content.replace(target, FONT_LINKS + target, 1)
    return content

def fix_bidi_arabic_content(content):
    # Fix broken parentheses around acronyms like (<bdi>ATS</bdi>) -> <bdi dir="ltr">(ATS)</bdi>
    content = re.sub(r'\(\s*<bdi>ATS</bdi>\s*\)', '<bdi dir="ltr">(ATS)</bdi>', content)
    content = re.sub(r'\(\s*<bdi>BiDi</bdi>\s*\)', '<bdi dir="ltr">(BiDi)</bdi>', content)
    content = re.sub(r'\(\s*<bdi>PDPL</bdi>\s*\)', '<bdi dir="ltr">(PDPL)</bdi>', content)
    content = re.sub(r'\(ATS\)', '<bdi dir="ltr">(ATS)</bdi>', content)
    content = re.sub(r'\(PDPL\)', '<bdi dir="ltr">(PDPL)</bdi>', content)
    content = re.sub(r'\(BiDi\)', '<bdi dir="ltr">(BiDi)</bdi>', content)
    content = re.sub(r'\(Remote\)', '<bdi dir="ltr">(Remote)</bdi>', content)
    content = re.sub(r'\(QR Code\)', '<bdi dir="ltr">(QR Code)</bdi>', content)
    content = re.sub(r'\(iOS & Android\)', '<bdi dir="ltr">(iOS & Android)</bdi>', content)
    content = re.sub(r'\(iOS &amp; Android\)', '<bdi dir="ltr">(iOS &amp; Android)</bdi>', content)

    # Job titles with parentheses
    content = re.sub(r'\(\s*<span dir="ltr">Senior Software Engineer</span>\s*\)', '<bdi dir="ltr">(Senior Software Engineer)</bdi>', content)
    content = re.sub(r'\(\s*<span dir="ltr">PMP Project Manager</span>\s*\)', '<bdi dir="ltr">(PMP Project Manager)</bdi>', content)
    content = re.sub(r'\(\s*<span dir="ltr">Digital Marketing Specialist</span>\s*\)', '<bdi dir="ltr">(Digital Marketing Specialist)</bdi>', content)

    # Fix footer legal entity string
    content = re.sub(
        r'<div><strong>الجهة المالكة والمشغلة:</strong>\s*(?:<bdi[^>]*>)?Digital Pass Establishment(?:</bdi>)?</div>',
        '<div><strong>الجهة المالكة والمشغلة:</strong> <bdi dir="ltr">Digital Pass Establishment</bdi></div>',
        content
    )
    content = re.sub(
        r'سجل تجاري رقم:\s*<strong[^>]*>(?:<bdi[^>]*>)?7051100811(?:</bdi>)?</strong>',
        'سجل تجاري رقم: <strong dir="ltr"><bdi>7051100811</bdi></strong>',
        content
    )
    content = re.sub(
        r'البريد:\s*<a href="mailto:info@siratie.com"[^>]*>(?:<bdi[^>]*>)?info@siratie.com(?:</bdi>)?</a>',
        'البريد: <a href="mailto:info@siratie.com" style="color: var(--color-primary-ink); font-weight: 600;" dir="ltr"><bdi>info@siratie.com</bdi></a>',
        content
    )
    content = re.sub(
        r'الهاتف:\s*<a href="tel:\+966591890300"[^>]*>(?:<bdi[^>]*>)?(?:&#x200E;|\u200E)?\+966 59 189 0300(?:</bdi>)?</a>',
        'الهاتف: <a href="tel:+966591890300" style="color: var(--color-text-secondary); text-decoration: none;" dir="ltr"><bdi>&#x200E;+966 59 189 0300</bdi></a>',
        content
    )
    # Fix invalid style="... dir: ltr;"
    content = re.sub(r'dir:\s*ltr;', 'direction: ltr;', content)

    # Ensure copyright string has dir="ltr"
    content = re.sub(
        r'<p style="([^"]*)" dir="ltr">Siratie is owned and operated by Digital Pass Establishment \(CR: 7051100811\)</p>',
        r'<p style="\1" dir="ltr">Siratie is owned and operated by Digital Pass Establishment (CR: 7051100811)</p>',
        content
    )
    content = re.sub(
        r'<p style="margin: 0; font-size: 0\.8125rem; color: var\(--color-text-secondary\);">Siratie is owned and operated by Digital Pass Establishment \(CR: 7051100811\)</p>',
        r'<p style="margin: 0; font-size: 0.8125rem; color: var(--color-text-secondary);" dir="ltr">Siratie is owned and operated by Digital Pass Establishment (CR: 7051100811)</p>',
        content
    )

    # Store download badges in footer
    content = re.sub(
        r'متوفر على\s*(?:<bdi[^>]*>)?App Store(?:</bdi>)?\s*و\s*(?:<bdi[^>]*>)?Google Play(?:</bdi>)?',
        'متوفر على <bdi dir="ltr">App Store</bdi> و <bdi dir="ltr">Google Play</bdi>',
        content
    )
    content = re.sub(
        r'يدعم\s*(?:<bdi[^>]*>)?iOS 15\.0(?:</bdi>)?\s*أو أحدث',
        'يدعم <bdi dir="ltr">iOS 15.0</bdi> أو أحدث',
        content
    )
    content = re.sub(
        r'يدعم\s*(?:<bdi[^>]*>)?Android 8\.0(?:</bdi>)?\s*أو أحدث',
        'يدعم <bdi dir="ltr">Android 8.0</bdi> أو أحدث',
        content
    )

    # In hero metrics
    content = re.sub(r'\+100,000 سيرة مفحوصة', '<bdi dir="ltr">+100,000</bdi> سيرة مفحوصة', content)
    content = re.sub(r'توافق <bdi>ATS</bdi> بنسبة 99\.8%', 'توافق <bdi>ATS</bdi> بنسبة <bdi dir="ltr">99.8%</bdi>', content)

    # In download sections: Apple App Store and Google Play
    content = re.sub(
        r'روابط التنزيل المباشرة من Apple App Store و Google Play',
        'روابط التنزيل المباشرة من <bdi dir="ltr">Apple App Store</bdi> و <bdi dir="ltr">Google Play</bdi>',
        content
    )
    content = re.sub(
        r'تطبيق سيرتي متاح لنظامي iOS وAndroid\.',
        'تطبيق سيرتي متاح لنظامي <bdi>iOS</bdi> و <bdi>Android</bdi>.',
        content
    )

    # Clean double <bdi> if any
    content = re.sub(r'<bdi dir="ltr"><bdi dir="ltr">([^<]+)</bdi></bdi>', r'<bdi dir="ltr">\1</bdi>', content)
    content = re.sub(r'<bdi><bdi>([^<]+)</bdi></bdi>', r'<bdi>\1</bdi>', content)

    return content

def process_file(file_path):
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()

    original = content
    # 1. Add font preconnect links
    content = add_font_links(content)

    # 2. If Arabic page, fix BiDi issues
    if "\\en\\" not in file_path and "/en/" not in file_path:
        content = fix_bidi_arabic_content(content)

    if content != original:
        with open(file_path, "w", encoding="utf-8") as f:
            f.write(content)
        print(f"Updated: {file_path}")
    else:
        print(f"Unchanged: {file_path}")

def main():
    for root, _, files in os.walk(PUBLIC_DIR):
        for f in files:
            if f.endswith(".html"):
                process_file(os.path.join(root, f))

if __name__ == "__main__":
    main()
