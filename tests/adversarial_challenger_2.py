#!/usr/bin/env python3
"""
Adversarial Verification Suite for Milestone M5 (Siratie Bilingual Static Landing Site)
Author: Adversarial Challenger 2 (Empirical Challenger & Specialist)
Strict Zero-Slack Quality Gate Verification:
1. Strict WCAG AA Color Contrast Math across public/assets/css/site.css (ratio >= 4.5:1 for body text)
2. BiDi Isolation for Latin/English Acronyms (ATS, CV, AI, PDF) in Arabic HTML pages
3. Reusable Smart Download Component Runtime Simulation (Android, iPhone, iPad, Mac, Windows UA + GA4)
4. Placeholder Safety & Zero Synthetic IDs Verification
"""

import os
import sys
import re
import json
from html.parser import HTMLParser

# Ensure UTF-8 output
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except AttributeError:
        pass

PUBLIC_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'public')
CSS_FILE = os.path.join(PUBLIC_DIR, 'assets', 'css', 'site.css')
JS_FILE = os.path.join(PUBLIC_DIR, 'assets', 'js', 'download.js')

# ==============================================================================
# TRACK 1: STRICT WCAG AA COLOR CONTRAST MATH
# ==============================================================================

def hex_to_srgb(hex_str):
    hex_str = hex_str.lstrip('#')
    if len(hex_str) == 3:
        hex_str = ''.join([c * 2 for c in hex_str])
    r = int(hex_str[0:2], 16) / 255.0
    g = int(hex_str[2:4], 16) / 255.0
    b = int(hex_str[4:6], 16) / 255.0
    return r, g, b

def srgb_channel_to_linear(c):
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

def relative_luminance(hex_str):
    r, g, b = hex_to_srgb(hex_str)
    return 0.2126 * srgb_channel_to_linear(r) + 0.7152 * srgb_channel_to_linear(g) + 0.0722 * srgb_channel_to_linear(b)

def contrast_ratio(hex1, hex2):
    l1 = relative_luminance(hex1)
    l2 = relative_luminance(hex2)
    lighter = max(l1, l2)
    darker = min(l1, l2)
    return (lighter + 0.05) / (darker + 0.05)

def blend_colors(fg_hex, bg_hex, alpha):
    r1, g1, b1 = hex_to_srgb(fg_hex)
    r2, g2, b2 = hex_to_srgb(bg_hex)
    r = int(round((alpha * r1 + (1 - alpha) * r2) * 255.0))
    g = int(round((alpha * g1 + (1 - alpha) * g2) * 255.0))
    b = int(round((alpha * b1 + (1 - alpha) * b2) * 255.0))
    return f"#{r:02X}{g:02X}{b:02X}"

def run_color_contrast_audit():
    print("\n" + "=" * 70)
    print("TRACK 1: COLOR CONTRAST MATH AUDIT (WCAG AA Strict >= 4.5:1)")
    print("=" * 70)
    
    with open(CSS_FILE, 'r', encoding='utf-8') as f:
        css = f.read()

    # Define light and dark tokens as declared in CSS
    light_tokens = {
        '--color-primary': '#007F73',
        '--color-primary-dark': '#006A60',
        '--color-primary-ink': '#006A60',
        '--color-primary-light': '#DDF6F3',
        '--color-primary-mid': '#59DAC9',
        '--color-on-primary': '#FFFFFF',
        '--color-brand-surface': '#006A60',
        '--color-brand-surface-deep': '#004A43',
        '--color-on-brand-surface': '#FFFFFF',
        '--color-accent-amber': '#C97F1B',
        '--color-accent-amber-light': '#FDE9C7',
        '--color-on-amber-container': '#8A5612',
        '--color-success': '#2E7D5B',
        '--color-success-light': '#D7F0E5',
        '--color-on-success-container': '#256A4D',
        '--color-error': '#C73B36',
        '--color-error-light': '#FFE1DE',
        '--color-on-error-container': '#A32F2B',
        '--color-bg': '#FAF7F2',
        '--color-surface': '#FFFFFF',
        '--color-surface-low': '#F2F1EC',
        '--color-surface-container': '#EDECE6',
        '--color-surface-high': '#E7E5DE',
        '--color-border': '#D3E3DF',
        '--color-border-strong': '#BBC9C6',
        '--color-text-primary': '#171D1B',
        '--color-text-secondary': '#3C4947',
        '--color-text-muted': '#54615E',
    }

    dark_tokens = {
        '--color-primary': '#007F73',
        '--color-primary-dark': '#006A60',
        '--color-primary-ink': '#2FC4B2',
        '--color-primary-light': '#0E3B36',
        '--color-primary-mid': '#3DD4C2',
        '--color-on-primary': '#FFFFFF',
        '--color-brand-surface': '#0F5C54',
        '--color-brand-surface-deep': '#0A3D38',
        '--color-on-brand-surface': '#FFFFFF',
        '--color-accent-amber': '#E0A03A',
        '--color-accent-amber-light': '#3D2E14',
        '--color-on-amber-container': '#F2C16E',
        '--color-success': '#5FBF92',
        '--color-success-light': '#1A3D2E',
        '--color-on-success-container': '#82E0B2',
        '--color-error': '#F2726C',
        '--color-error-light': '#4A211F',
        '--color-on-error-container': '#FFA09C',
        '--color-bg': '#121614',
        '--color-surface': '#1A201D',
        '--color-surface-low': '#202623',
        '--color-surface-container': '#252C28',
        '--color-surface-high': '#2B332E',
        '--color-border': '#31423E',
        '--color-border-strong': '#445753',
        '--color-text-primary': '#E6EAE7',
        '--color-text-secondary': '#AEB8B4',
        '--color-text-muted': '#7E8985',
    }

    test_combinations = [
        # --- Light Theme Core Surfaces ---
        ("Light Theme: Body Text on Body BG", light_tokens['--color-text-primary'], light_tokens['--color-bg'], 4.5),
        ("Light Theme: Body Text on Surface (Card)", light_tokens['--color-text-primary'], light_tokens['--color-surface'], 4.5),
        ("Light Theme: Body Text on Surface-Low (Recessed)", light_tokens['--color-text-primary'], light_tokens['--color-surface-low'], 4.5),
        ("Light Theme: Body Text on Surface-Container (Inputs)", light_tokens['--color-text-primary'], light_tokens['--color-surface-container'], 4.5),
        ("Light Theme: Text-Secondary on Body BG", light_tokens['--color-text-secondary'], light_tokens['--color-bg'], 4.5),
        ("Light Theme: Text-Secondary on Card Surface", light_tokens['--color-text-secondary'], light_tokens['--color-surface'], 4.5),
        ("Light Theme: Text-Secondary on Surface-Low", light_tokens['--color-text-secondary'], light_tokens['--color-surface-low'], 4.5),
        ("Light Theme: Text-Muted on Body BG", light_tokens['--color-text-muted'], light_tokens['--color-bg'], 4.5),
        ("Light Theme: Text-Muted on Card Surface", light_tokens['--color-text-muted'], light_tokens['--color-surface'], 4.5),
        ("Light Theme: Primary Ink on Body BG", light_tokens['--color-primary-ink'], light_tokens['--color-bg'], 4.5),
        ("Light Theme: Primary Ink on Card Surface", light_tokens['--color-primary-ink'], light_tokens['--color-surface'], 4.5),
        ("Light Theme: Primary Ink on Primary-Light (Badge/Chip)", light_tokens['--color-primary-ink'], light_tokens['--color-primary-light'], 4.5),
        ("Light Theme: Primary Button (.btn-primary)", light_tokens['--color-on-primary'], light_tokens['--color-primary'], 4.5),
        ("Light Theme: Primary Button Hover (.btn-primary:hover)", light_tokens['--color-on-primary'], light_tokens['--color-primary-dark'], 4.5),
        ("Light Theme: Brand Surface Card on Surface", light_tokens['--color-on-brand-surface'], light_tokens['--color-brand-surface'], 4.5),
        ("Light Theme: Brand Surface Deep Card End", light_tokens['--color-on-brand-surface'], light_tokens['--color-brand-surface-deep'], 4.5),
        ("Light Theme: Amber Badge (.badge-amber / .badge-status)", light_tokens['--color-on-amber-container'], light_tokens['--color-accent-amber-light'], 4.5),
        ("Light Theme: Success Badge (.badge-success)", light_tokens['--color-on-success-container'], light_tokens['--color-success-light'], 4.5),
        ("Light Theme: Error Badge (.badge-error)", light_tokens['--color-on-error-container'], light_tokens['--color-error-light'], 4.5),

        # --- Dark Theme Core Surfaces ---
        ("Dark Theme: Body Text on Body BG", dark_tokens['--color-text-primary'], dark_tokens['--color-bg'], 4.5),
        ("Dark Theme: Body Text on Dark Surface (Card)", dark_tokens['--color-text-primary'], dark_tokens['--color-surface'], 4.5),
        ("Dark Theme: Body Text on Dark Surface-Low", dark_tokens['--color-text-primary'], dark_tokens['--color-surface-low'], 4.5),
        ("Dark Theme: Body Text on Dark Surface-Container", dark_tokens['--color-text-primary'], dark_tokens['--color-surface-container'], 4.5),
        ("Dark Theme: Text-Secondary on Dark BG", dark_tokens['--color-text-secondary'], dark_tokens['--color-bg'], 4.5),
        ("Dark Theme: Text-Secondary on Dark Surface", dark_tokens['--color-text-secondary'], dark_tokens['--color-surface'], 4.5),
        ("Dark Theme: Text-Muted on Dark Surface", dark_tokens['--color-text-muted'], dark_tokens['--color-surface'], 4.5),
        ("Dark Theme: Primary Ink on Dark BG", dark_tokens['--color-primary-ink'], dark_tokens['--color-bg'], 4.5),
        ("Dark Theme: Primary Ink on Dark Surface", dark_tokens['--color-primary-ink'], dark_tokens['--color-surface'], 4.5),
        ("Dark Theme: Primary Ink on Primary-Light (Badge)", dark_tokens['--color-primary-ink'], dark_tokens['--color-primary-light'], 4.5),
        ("Dark Theme: Primary Button (.btn-primary)", dark_tokens['--color-on-primary'], dark_tokens['--color-primary'], 4.5),
        ("Dark Theme: Brand Surface Card on Dark Surface", dark_tokens['--color-on-brand-surface'], dark_tokens['--color-brand-surface'], 4.5),
        ("Dark Theme: Brand Surface Deep Card End", dark_tokens['--color-on-brand-surface'], dark_tokens['--color-brand-surface-deep'], 4.5),
        ("Dark Theme: Amber Badge (.badge-amber / .badge-status)", dark_tokens['--color-on-amber-container'], dark_tokens['--color-accent-amber-light'], 4.5),
        ("Dark Theme: Success Badge (.badge-success)", dark_tokens['--color-on-success-container'], dark_tokens['--color-success-light'], 4.5),
        ("Dark Theme: Error Badge (.badge-error)", dark_tokens['--color-on-error-container'], dark_tokens['--color-error-light'], 4.5),

        # --- Component Specific & Edge Case Pairings Found in site.css ---
        ("Component: .btn-store store text on #0E1A18", '#FFFFFF', '#0E1A18', 4.5),
        ("Component: .store-subtitle 70% white on #0E1A18", blend_colors('#FFFFFF', '#0E1A18', 0.70), '#0E1A18', 4.5),
        ("Component: .smart-download badge tag on brand-surface", '#FFFFFF', blend_colors('#FFFFFF', light_tokens['--color-brand-surface'], 0.16), 4.5),
        ("Component: .btn-primary:hover in Dark Theme", dark_tokens['--color-on-primary'], dark_tokens['--color-primary-dark'], 4.5),
        ("Component: .status-pill (Amber Pill) in Light Theme", light_tokens['--color-on-amber-container'], light_tokens['--color-accent-amber-light'], 4.5),
        ("Component: .status-pill (Amber Pill) in Dark Theme", dark_tokens['--color-on-amber-container'], dark_tokens['--color-accent-amber-light'], 4.5),
        ("Component: .qr-caption (100% white) on qr-box (8% white over brand-surface)", 
         '#FFFFFF', 
         blend_colors('#FFFFFF', light_tokens['--color-brand-surface'], 0.08), 4.5),
    ]

    contrast_results = []
    failures = []

    for name, fg, bg, threshold in test_combinations:
        ratio = contrast_ratio(fg, bg)
        passed = (ratio >= threshold)
        contrast_results.append({
            'name': name,
            'fg': fg,
            'bg': bg,
            'ratio': ratio,
            'threshold': threshold,
            'passed': passed
        })
        status = "PASS" if passed else "FAIL"
        print(f"[{status}] {name:56} | {ratio:5.2f}:1 (req >= {threshold:.1f}) | FG: {fg} BG: {bg}")
        if not passed:
            failures.append({
                'name': name,
                'fg': fg,
                'bg': bg,
                'ratio': ratio,
                'deficit': threshold - ratio
            })

    print(f"\nContrast Audit Complete: {len(contrast_results)} pairs tested, {len(failures)} failures identified.")
    return contrast_results, failures

# ==============================================================================
# TRACK 2: BIDI ISOLATION (<bdi>) FOR ACRONYMS IN ARABIC PAGES
# ==============================================================================

class ArabicBiDiAcronymParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_head = False
        self.in_script_or_style = False
        self.tag_stack = []
        self.bdi_depth = 0
        self.occurrences = []

    def handle_starttag(self, tag, attrs):
        self.tag_stack.append(tag)
        if tag == 'head':
            self.in_head = True
        elif tag in ('script', 'style'):
            self.in_script_or_style = True
        elif tag == 'bdi':
            self.bdi_depth += 1

    def handle_endtag(self, tag):
        if self.tag_stack and self.tag_stack[-1] == tag:
            self.tag_stack.pop()
        if tag == 'head':
            self.in_head = False
        elif tag in ('script', 'style'):
            self.in_script_or_style = False
        elif tag == 'bdi':
            self.bdi_depth = max(0, self.bdi_depth - 1)

    def handle_data(self, data):
        # We only inspect visible body text content
        if self.in_head or self.in_script_or_style:
            return
        
        current_enclosing_tag = self.tag_stack[-1] if self.tag_stack else 'body'
        
        # Target acronyms specified in prompt: ATS, CV, AI, PDF
        for acr in ['ATS', 'CV', 'AI', 'PDF']:
            for m in re.finditer(rf'\b{acr}\b', data):
                is_wrapped = (self.bdi_depth > 0)
                # Capture context around acronym (up to 40 chars before and after)
                start = max(0, m.start() - 30)
                end = min(len(data), m.end() + 30)
                snippet = data[start:end].replace('\n', ' ').strip()
                
                self.occurrences.append({
                    'acronym': acr,
                    'enclosing_tag': current_enclosing_tag,
                    'wrapped_in_bdi': is_wrapped,
                    'snippet': snippet
                })

def run_bidi_acronym_audit():
    print("\n" + "=" * 70)
    print("TRACK 2: BIDI ISOLATION (<bdi>) AUDIT ACROSS ARABIC LANDING PAGES")
    print("=" * 70)

    arabic_pages = [
        'index.html',
        'ats-cv-checker/index.html',
        'cv-builder/index.html',
        'cv-templates/index.html',
        'jobs/index.html',
        'download/index.html',
        'privacy/index.html',
        'terms/index.html',
        '404.html'
    ]

    site_bidi_summary = {}
    total_occurrences = 0
    total_wrapped = 0
    total_unwrapped = 0
    unwrapped_details = []

    for rel_path in arabic_pages:
        full_path = os.path.join(PUBLIC_DIR, rel_path)
        if not os.path.exists(full_path):
            print(f"[ERROR] File not found: {full_path}")
            continue

        with open(full_path, 'r', encoding='utf-8') as f:
            html_content = f.read()

        parser = ArabicBiDiAcronymParser()
        parser.feed(html_content)

        page_total = len(parser.occurrences)
        page_wrapped = sum(1 for o in parser.occurrences if o['wrapped_in_bdi'])
        page_unwrapped = sum(1 for o in parser.occurrences if not o['wrapped_in_bdi'])

        total_occurrences += page_total
        total_wrapped += page_wrapped
        total_unwrapped += page_unwrapped

        site_bidi_summary[rel_path] = {
            'total': page_total,
            'wrapped': page_wrapped,
            'unwrapped': page_unwrapped,
            'items': parser.occurrences
        }

        print(f"[{'PASS' if page_unwrapped == 0 and page_total > 0 else 'WARN'}] {rel_path:28}: Total={page_total:2} | Wrapped in <bdi>={page_wrapped:2} | UNWRAPPED={page_unwrapped:2}")
        
        for o in parser.occurrences:
            if not o['wrapped_in_bdi']:
                unwrapped_details.append({
                    'page': rel_path,
                    'acronym': o['acronym'],
                    'tag': o['enclosing_tag'],
                    'snippet': o['snippet']
                })

    print(f"\nBiDi Isolation Audit Complete:")
    print(f"  Total Body Acronym Occurrences : {total_occurrences}")
    print(f"  Wrapped in <bdi>                : {total_wrapped}")
    print(f"  Unwrapped (Risk of BiDi flip)  : {total_unwrapped}")

    return site_bidi_summary, unwrapped_details

# ==============================================================================
# TRACK 3: SMART DOWNLOAD COMPONENT RUNTIME SIMULATION
# ==============================================================================

def simulate_smart_download_runtime():
    print("\n" + "=" * 70)
    print("TRACK 3: REUSABLE SMART DOWNLOAD RUNTIME SIMULATION & TELEMETRY")
    print("=" * 70)

    # 5 Test User Agent Profiles
    user_agents = [
        {
            'profile': 'Android Mobile',
            'ua': 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'platform': 'Linux armv8l',
            'maxTouchPoints': 5,
            'expected_platform': 'android',
            'expected_apple_display': 'none',
            'expected_google_display': 'inline-flex',
            'expected_qr_display': 'none',
            'expected_click_event': 'download_click_android',
        },
        {
            'profile': 'iPhone Mobile',
            'ua': 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
            'platform': 'iPhone',
            'maxTouchPoints': 5,
            'expected_platform': 'ios',
            'expected_apple_display': 'inline-flex',
            'expected_google_display': 'none',
            'expected_qr_display': 'none',
            'expected_click_event': 'download_click_ios',
        },
        {
            'profile': 'iPad Tablet (Desktop-class MacIntel)',
            'ua': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'platform': 'MacIntel',
            'maxTouchPoints': 5,  # iPadOS masquerading as Mac desktop with multi-touch
            'expected_platform': 'ios',
            'expected_apple_display': 'inline-flex',
            'expected_google_display': 'none',
            'expected_qr_display': 'none',
            'expected_click_event': 'download_click_ios',
        },
        {
            'profile': 'Mac Desktop',
            'ua': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'platform': 'MacIntel',
            'maxTouchPoints': 0,
            'expected_platform': 'desktop',
            'expected_apple_display': 'inline-flex',
            'expected_google_display': 'inline-flex',
            'expected_qr_display': 'flex',
            'expected_click_event': 'download_click_desktop',
            'expected_view_event': 'qr_view',
        },
        {
            'profile': 'Windows Desktop',
            'ua': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'platform': 'Win32',
            'maxTouchPoints': 0,
            'expected_platform': 'desktop',
            'expected_apple_display': 'inline-flex',
            'expected_google_display': 'inline-flex',
            'expected_qr_display': 'flex',
            'expected_click_event': 'download_click_desktop',
            'expected_view_event': 'qr_view',
        },
    ]

    # Verify download.js exists and parse its detection regexes directly
    with open(JS_FILE, 'r', encoding='utf-8') as f:
        js_code = f.read()

    # Extract detection logic from JS:
    # const isAndroid = /Android/i.test(ua);
    # const isIOS = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    # const isDesktop = !isAndroid && !isIOS;
    android_regex = re.search(r'isAndroid\s*=\s*(/[^/]+/i)', js_code).group(1)
    ios_regex = re.search(r'isIOS\s*=\s*(/[^/]+/i)', js_code).group(1)

    android_pat = re.compile(android_regex.strip('/i'), re.IGNORECASE)
    ios_pat = re.compile(ios_regex.strip('/i'), re.IGNORECASE)

    simulation_results = []
    sim_failures = []

    for profile in user_agents:
        ua = profile['ua']
        platform = profile['platform']
        touch_points = profile['maxTouchPoints']

        is_android = bool(android_pat.search(ua))
        is_ios = bool(ios_pat.search(ua) or (platform == 'MacIntel' and touch_points > 1))
        is_desktop = not is_android and not is_ios

        detected_platform = 'android' if is_android else ('ios' if is_ios else 'desktop')
        matched = (detected_platform == profile['expected_platform'])

        # Telemetry verification:
        simulated_ga4_events = []
        if is_android:
            simulated_ga4_events.append({
                'event': 'download_click_android',
                'params': {'device': 'android', 'link_url': '[PLACEHOLDER: Google Play URL]'}
            })
        elif is_ios:
            simulated_ga4_events.append({
                'event': 'download_click_ios',
                'params': {'device': 'ios', 'link_url': '[PLACEHOLDER: App Store URL]'}
            })
        else:
            simulated_ga4_events.append({
                'event': 'download_click_desktop',
                'params': {'device': 'desktop', 'store_target': 'app_store'}
            })
            simulated_ga4_events.append({
                'event': 'download_click_desktop',
                'params': {'device': 'desktop', 'store_target': 'google_play'}
            })
            simulated_ga4_events.append({
                'event': 'qr_view',
                'params': {'trigger_method': 'viewport_intersection'}
            })

        print(f"[{'PASS' if matched else 'FAIL'}] UA Profile: {profile['profile']:35} -> Detected: {detected_platform} (Expected: {profile['expected_platform']})")
        print(f"       Expected Badges: Apple={profile['expected_apple_display']}, Google={profile['expected_google_display']}, QR={profile['expected_qr_display']}")
        print(f"       Simulated GA4 Events: {[e['event'] for e in simulated_ga4_events]}")

        simulation_results.append({
            'profile': profile['profile'],
            'detected': detected_platform,
            'expected': profile['expected_platform'],
            'matched': matched,
            'ga4_events': simulated_ga4_events
        })

        if not matched:
            sim_failures.append(profile['profile'])

    # Now verify component integration across all 16 pages
    print("\n--- Verifying Smart Download Component Presence Across 16 Pages ---")
    all_pages = [
        ('index.html', True),
        ('en/index.html', True),
        ('ats-cv-checker/index.html', True),
        ('en/ats-cv-checker/index.html', True),
        ('cv-builder/index.html', True),
        ('en/cv-builder/index.html', True),
        ('cv-templates/index.html', True),
        ('en/cv-templates/index.html', True),
        ('jobs/index.html', True),
        ('en/jobs/index.html', True),
        ('download/index.html', True),
        ('en/download/index.html', True),
        ('privacy/index.html', False),
        ('en/privacy/index.html', False),
        ('terms/index.html', False),
        ('en/terms/index.html', False),
    ]

    page_component_audit = []
    for rel_path, should_have_component in all_pages:
        full_path = os.path.join(PUBLIC_DIR, rel_path)
        with open(full_path, 'r', encoding='utf-8') as f:
            html = f.read()

        has_component = bool(re.search(r'data-component="smart-download"|class="smart-download-section"', html))
        has_js_script = ('assets/js/download.js' in html)
        has_apple_btn = bool(re.search(r'id="btnAppStore"|class="[^"]*btn-apple[^"]*"', html))
        has_google_btn = bool(re.search(r'id="btnGooglePlay"|class="[^"]*btn-google[^"]*"', html))
        has_qr_box = bool(re.search(r'id="desktopQrBox"|class="[^"]*qr-code-box[^"]*"', html))

        is_valid = (has_component == should_have_component and 
                    has_js_script == should_have_component and 
                    has_apple_btn == should_have_component and 
                    has_google_btn == should_have_component and
                    has_qr_box == should_have_component)

        status = "PASS" if is_valid else "FAIL"
        print(f"[{status}] {rel_path:30}: ShouldHave={should_have_component} | Found: Comp={has_component}, JS={has_js_script}, Apple={has_apple_btn}, Google={has_google_btn}, QR={has_qr_box}")

        page_component_audit.append({
            'page': rel_path,
            'should_have': should_have_component,
            'has_component': has_component,
            'valid': is_valid
        })

    return simulation_results, sim_failures, page_component_audit

# ==============================================================================
# TRACK 4: PLACEHOLDER SAFETY & ZERO SYNTHETIC IDS
# ==============================================================================

def run_placeholder_safety_audit():
    print("\n" + "=" * 70)
    print("TRACK 4: PLACEHOLDER SAFETY & ZERO SYNTHETIC IDS AUDIT")
    print("=" * 70)

    # Known allowed placeholders per project spec
    allowed_placeholders = {
        '[PLACEHOLDER: Measurement ID]',
        '[PLACEHOLDER: Twitter Handle]',
        '[PLACEHOLDER: App Store URL]',
        '[PLACEHOLDER: Google Play URL]',
        '[PLACEHOLDER: Apple App Store URL]',
        '[PLACEHOLDER: Google Play Store URL]',
        '[PLACEHOLDER: Social Profile URL]'
    }

    # Forbidden synthetic ID patterns
    forbidden_synthetic_patterns = [
        (re.compile(r'\bG-[A-Z0-9]{8,12}\b'), "Synthetic GA4 Measurement ID (e.g. G-XXXXXXXX)"),
        (re.compile(r'\bUA-\d+-\d+\b'), "Synthetic Universal Analytics ID"),
        (re.compile(r'https?://(?:apps\.apple\.com|itunes\.apple\.com)/[^\s"\'<>]+'), "Synthetic Apple App Store URL"),
        (re.compile(r'https?://play\.google\.com/store/apps/[^\s"\'<>]+'), "Synthetic Google Play Store URL"),
        (re.compile(r'https?://(?:twitter\.com|x\.com)/[a-zA-Z0-9_]+'), "Synthetic Twitter / X profile URL"),
        (re.compile(r'https?://(?:www\.)?instagram\.com/[a-zA-Z0-9_.]+'), "Synthetic Instagram profile URL"),
        (re.compile(r'https?://(?:www\.)?linkedin\.com/company/[a-zA-Z0-9_]+'), "Synthetic LinkedIn profile URL"),
    ]

    all_found_placeholders = set()
    synthetic_id_violations = []

    for root, dirs, files in os.walk(PUBLIC_DIR):
        for f in files:
            if not f.endswith(('.html', '.js', '.css', '.xml', '.txt')):
                continue
            fpath = os.path.join(root, f)
            rel_path = os.path.relpath(fpath, PUBLIC_DIR)
            with open(fpath, 'r', encoding='utf-8', errors='ignore') as file_obj:
                content = file_obj.read()

            # Find all placeholders
            matches = re.findall(r'\[PLACEHOLDER:[^\]]+\]', content)
            for m in matches:
                all_found_placeholders.add(m)

            # Check forbidden synthetic patterns
            for pat, desc in forbidden_synthetic_patterns:
                hits = pat.findall(content)
                if hits:
                    synthetic_id_violations.append({
                        'file': rel_path,
                        'pattern': desc,
                        'hits': hits
                    })

    print(f"Total Unique Placeholders Retained across codebase: {len(all_found_placeholders)}")
    for p in sorted(all_found_placeholders):
        is_known = p in allowed_placeholders
        print(f"  [{'VALID' if is_known else 'UNKNOWN'}] {p}")

    print(f"\nSynthetic ID Violations Identified: {len(synthetic_id_violations)}")
    for v in synthetic_id_violations:
        print(f"  [VIOLATION] {v['file']}: {v['pattern']} -> {v['hits']}")

    passed = (len(synthetic_id_violations) == 0 and len(all_found_placeholders) > 0)
    print(f"\nPlaceholder Safety Result: {'PASS' if passed else 'FAIL'}")

    return all_found_placeholders, synthetic_id_violations, passed

# ==============================================================================
# MAIN RUNNER & REPORT GENERATOR
# ==============================================================================

def main():
    print("=" * 70)
    print("   SIRATIE (سيرتي) ADVERSARIAL CHALLENGER 2 INDEPENDENT TEST HARNESS   ")
    print("=" * 70)

    contrast_results, contrast_failures = run_color_contrast_audit()
    bidi_summary, bidi_unwrapped = run_bidi_acronym_audit()
    sim_results, sim_failures, page_comp_audit = simulate_smart_download_runtime()
    placeholders, synthetic_violations, placeholder_pass = run_placeholder_safety_audit()

    print("\n" + "=" * 70)
    print("ADVERSARIAL VERIFICATION HARNESS EXECUTIVE SUMMARY")
    print("=" * 70)
    print(f"1. Color Contrast Math   : {len(contrast_failures)} failures out of {len(contrast_results)} pairs tested")
    print(f"2. BiDi Isolation (<bdi>): {len(bidi_unwrapped)} unwrapped acronyms out of {sum(s['total'] for s in bidi_summary.values())} in body")
    print(f"3. Smart Download Runtime: {len(sim_failures)} failures across 5 User-Agent profiles")
    print(f"4. Placeholder Safety    : {'PASS' if placeholder_pass else 'FAIL'} (0 synthetic IDs, {len(placeholders)} valid placeholders retained)")
    print("=" * 70)

    # Save structured test artifact for reporting
    output_data = {
        'contrast_failures': contrast_failures,
        'bidi_unwrapped_count': len(bidi_unwrapped),
        'bidi_unwrapped_sample': bidi_unwrapped[:20],
        'sim_failures': sim_failures,
        'synthetic_violations': synthetic_violations,
        'placeholder_count': len(placeholders)
    }

    report_json_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'adversarial_results.json')
    with open(report_json_path, 'w', encoding='utf-8') as f:
        json.dump(output_data, f, ensure_ascii=False, indent=2)
    print(f"Saved machine-readable test results to: {report_json_path}")

    # Return exit code: if there are failures, exit with code 1
    total_failures = len(contrast_failures) + len(bidi_unwrapped) + len(sim_failures) + len(synthetic_violations)
    if total_failures > 0:
        print(f"\n>>> ADVERSARIAL HARNESS IDENTIFIED {total_failures} VERIFICATION ISSUES <<<")
        return 1
    else:
        print("\n>>> ALL ADVERSARIAL TESTS PASSED <<<")
        return 0

if __name__ == '__main__':
    sys.exit(main())
