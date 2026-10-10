"""
Generate production assets for Milestone M1 of Siratie static site:
- public/favicon.ico (multi-resolution 16x16, 32x32, 48x48)
- public/apple-touch-icon.png (180x180)
- public/assets/images/*.webp & public/images/*.webp
"""

import os
import math
from PIL import Image, ImageDraw, ImageFont

ROOT_DIR = r"d:\Sirati"
PUBLIC_DIR = os.path.join(ROOT_DIR, "public")
ASSETS_IMG_DIR = os.path.join(PUBLIC_DIR, "assets", "images")
PUBLIC_IMG_DIR = os.path.join(PUBLIC_DIR, "images")

os.makedirs(ASSETS_IMG_DIR, exist_ok=True)
os.makedirs(PUBLIC_IMG_DIR, exist_ok=True)

# Master sources
ICON_1024_PATH = os.path.join(ROOT_DIR, "flutter_app", "assets", "branding", "sirati_app_icon_1024.png")
DOCS_VERIFY_DIR = os.path.join(ROOT_DIR, "docs", "verification")
FONT_BOLD_PATH = os.path.join(ROOT_DIR, "resources", "fonts", "ibm-plex-sans-arabic", "IBMPlexSansArabic-Bold.ttf")
FONT_REG_PATH = os.path.join(ROOT_DIR, "resources", "fonts", "ibm-plex-sans-arabic", "IBMPlexSansArabic-Regular.ttf")
FONT_SEMI_PATH = os.path.join(ROOT_DIR, "resources", "fonts", "ibm-plex-sans-arabic", "IBMPlexSansArabic-SemiBold.ttf")

def get_font(size, weight="regular"):
    try:
        if weight == "bold" and os.path.exists(FONT_BOLD_PATH):
            return ImageFont.truetype(FONT_BOLD_PATH, size)
        elif weight == "semi" and os.path.exists(FONT_SEMI_PATH):
            return ImageFont.truetype(FONT_SEMI_PATH, size)
        elif os.path.exists(FONT_REG_PATH):
            return ImageFont.truetype(FONT_REG_PATH, size)
    except Exception:
        pass
    return ImageFont.load_default()

def save_dual(img, filename):
    p1 = os.path.join(ASSETS_IMG_DIR, filename)
    p2 = os.path.join(PUBLIC_IMG_DIR, filename)
    img.save(p1, "WEBP", quality=90)
    img.save(p2, "WEBP", quality=90)
    print(f"Saved {filename} -> {p1} and {p2}")

# 1. Favicons
print("Generating Favicons...")
if os.path.exists(ICON_1024_PATH):
    master_icon = Image.open(ICON_1024_PATH).convert("RGBA")
    
    # apple-touch-icon.png (180x180)
    apple_icon = master_icon.resize((180, 180), Image.Resampling.LANCZOS)
    apple_icon.save(os.path.join(PUBLIC_DIR, "apple-touch-icon.png"), "PNG")
    print("Saved apple-touch-icon.png")

    # favicon.ico (16, 32, 48)
    ico_img = master_icon.resize((48, 48), Image.Resampling.LANCZOS)
    ico_img.save(
        os.path.join(PUBLIC_DIR, "favicon.ico"),
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48)]
    )
    print("Saved favicon.ico")
else:
    print("Warning: master icon not found at", ICON_1024_PATH)

# 2. Template previews from real verification renders
print("Generating Template Previews...")
templates = [
    ("raster_verify_ats-classic-professional.png", "cv-template-classic.webp"),
    ("raster_verify_bilingual-global-professional.png", "cv-template-modern.webp"),
    ("raster_verify_executive-leadership-brief.png", "cv-template-executive.webp"),
]

for src_name, dest_name in templates:
    src_path = os.path.join(DOCS_VERIFY_DIR, src_name)
    if os.path.exists(src_path):
        src_img = Image.open(src_path).convert("RGBA")
        # Target size 480x680 card
        card = Image.new("RGBA", (480, 680), (250, 247, 242, 255))
        draw = ImageDraw.Draw(card)
        # Background container
        draw.rounded_rectangle([10, 10, 470, 670], radius=16, fill=(255, 255, 255, 255), outline=(211, 227, 223, 255), width=2)
        
        # Scale template document to fit inside nicely
        doc_w, doc_h = 420, 594
        scaled_doc = src_img.resize((doc_w, doc_h), Image.Resampling.LANCZOS)
        # Add shadow / border around doc
        doc_x, doc_y = (480 - doc_w) // 2, 25
        card.paste(scaled_doc, (doc_x, doc_y), scaled_doc)
        draw.rectangle([doc_x, doc_y, doc_x + doc_w, doc_y + doc_h], outline=(200, 215, 210, 255), width=1)
        
        # Bottom label
        font = get_font(14, "semi")
        draw.text((240, 642), "ATS 100% Parsable · PDF/A-3", fill=(0, 106, 96, 255), font=font, anchor="mm")
        
        save_dual(card.convert("RGB"), dest_name)

# 3. QR Code (240x240)
print("Generating QR Download Mockup...")
def create_qr():
    img = Image.new("RGB", (240, 240), (255, 255, 255))
    draw = ImageDraw.Draw(img)
    # Draw finder patterns at corners
    def finder(x, y):
        draw.rectangle([x, y, x + 56, y + 56], fill=(18, 22, 20))
        draw.rectangle([x + 8, y + 8, x + 48, y + 48], fill=(255, 255, 255))
        draw.rectangle([x + 16, y + 16, x + 40, y + 40], fill=(0, 127, 115))
        
    finder(16, 16)
    finder(168, 16)
    finder(16, 168)
    
    # Timing patterns & modules
    for i in range(16, 224, 8):
        for j in range(16, 224, 8):
            # pseudo-matrix pattern
            if (i > 80 and i < 160) or (j > 80 and j < 160) or ((i * j) % 23 == 0) or ((i + j) % 17 == 0):
                if not ((i < 80 and j < 80) or (i > 150 and j < 80) or (i < 80 and j > 150)):
                    draw.rectangle([i, j, i + 6, j + 6], fill=(18, 22, 20))

    # Center logo chip
    draw.rounded_rectangle([92, 92, 148, 148], radius=8, fill=(0, 106, 96))
    draw.ellipse([110, 110, 130, 130], fill=(255, 255, 255))
    save_dual(img, "qr-download.webp")

create_qr()

# 4. Hero App Mockup (640x540)
print("Generating Hero App Mockup...")
def create_hero_mockup():
    img = Image.new("RGB", (640, 540), (250, 247, 242))
    draw = ImageDraw.Draw(img)
    
    # Ambient background card
    draw.rounded_rectangle([30, 20, 610, 520], radius=20, fill=(255, 255, 255), outline=(211, 227, 223), width=2)
    
    # Mobile Phone Frame (centered, 280x460)
    px, py, pw, ph = 180, 45, 280, 455
    draw.rounded_rectangle([px, py, px + pw, py + ph], radius=32, fill=(18, 22, 20), outline=(0, 106, 96), width=3)
    
    # Phone Screen (264x440)
    sx, sy, sw, sh = px + 8, py + 8, pw - 16, ph - 16
    draw.rounded_rectangle([sx, sy, sx + sw, sy + sh], radius=24, fill=(26, 32, 29))
    
    # Screen UI Header
    draw.rounded_rectangle([sx + 80, sy + 8, sx + sw - 80, sy + 22], radius=7, fill=(18, 22, 20)) # Dynamic island
    draw.text((sx + 20, sy + 38), "Siratie · سيرتي", fill=(230, 234, 231), font=get_font(13, "bold"))
    
    # ATS Score Widget on phone
    draw.rounded_rectangle([sx + 15, sy + 65, sx + sw - 15, sy + 185], radius=16, fill=(15, 92, 84))
    draw.text((sx + sw // 2, sy + 85), "ATS Compatibility", fill=(221, 246, 243), font=get_font(11, "regular"), anchor="mm")
    draw.text((sx + sw // 2, sy + 120), "94%", fill=(255, 255, 255), font=get_font(28, "bold"), anchor="mm")
    draw.text((sx + sw // 2, sy + 155), "توافق ممتاز مع أنظمة الفرز", fill=(89, 218, 201), font=get_font(11, "semi"), anchor="mm")
    
    # CV Items on phone screen
    draw.rounded_rectangle([sx + 15, sy + 195, sx + sw - 15, sy + 265], radius=12, fill=(37, 44, 40))
    draw.text((sx + 28, sy + 210), "قالب كلاسيكي معتمد", fill=(230, 234, 231), font=get_font(11, "bold"))
    draw.text((sx + 28, sy + 232), "مهندس برمجيات · جاهز للتصدير", fill=(174, 184, 180), font=get_font(10, "regular"))
    
    # Floating Pill 1 (Left): ATS Score Passed
    draw.rounded_rectangle([45, 140, 165, 210], radius=14, fill=(255, 255, 255), outline=(0, 127, 115), width=2)
    draw.text((105, 162), "ATS 94%", fill=(0, 106, 96), font=get_font(15, "bold"), anchor="mm")
    draw.text((105, 188), "اجتياز الفرز الآلي", fill=(60, 73, 71), font=get_font(11, "semi"), anchor="mm")
    
    # Floating Pill 2 (Right): Saudi Jobs Matching
    draw.rounded_rectangle([475, 280, 605, 350], radius=14, fill=(255, 255, 255), outline=(201, 127, 27), width=2)
    draw.text((540, 302), "+120 وظيفة", fill=(201, 127, 27), font=get_font(15, "bold"), anchor="mm")
    draw.text((540, 328), "مطابقة ذكية للرياض", fill=(60, 73, 71), font=get_font(11, "semi"), anchor="mm")
    
    save_dual(img, "hero-app-mockup.webp")

create_hero_mockup()

# 5. ATS Scanner Mockup (600x440)
print("Generating ATS Scanner Mockup...")
def create_ats_scanner_mockup():
    img = Image.new("RGB", (600, 440), (250, 247, 242))
    draw = ImageDraw.Draw(img)
    
    # Container
    draw.rounded_rectangle([20, 20, 580, 420], radius=16, fill=(255, 255, 255), outline=(211, 227, 223), width=2)
    
    # Header
    draw.text((300, 50), "تقرير فحص السيرة الذاتية (ATS Scan Report)", fill=(23, 29, 27), font=get_font(17, "bold"), anchor="mm")
    
    # Circular Gauge Simulator (Left/Center)
    cx, cy, r = 130, 175, 65
    draw.ellipse([cx - r, cy - r, cx + r, cy + r], fill=(221, 246, 243), outline=(0, 106, 96), width=8)
    draw.text((cx, cy - 8), "94%", fill=(0, 106, 96), font=get_font(26, "bold"), anchor="mm")
    draw.text((cx, cy + 20), "توافق ممتاز", fill=(46, 125, 91), font=get_font(12, "bold"), anchor="mm")
    
    # Metrics breakdown bars
    metrics = [
        ("بنية التنسيق والأعمدة (Single-Column Structure)", 100, (46, 125, 91)),
        ("الكلمات المفتاحية المهنية (Keywords Match)", 92, (0, 127, 115)),
        ("بيانات التواصل والروابط (Contact & BiDi)", 100, (46, 125, 91)),
        ("عناوين الأقسام المعتمدة (Section Headers)", 96, (0, 127, 115)),
    ]
    
    bx = 225
    by = 100
    for title, score, color in metrics:
        draw.text((bx, by), title, fill=(60, 73, 71), font=get_font(12, "semi"))
        draw.text((545, by), f"{score}%", fill=color, font=get_font(12, "bold"), anchor="ra")
        # Bar background
        draw.rounded_rectangle([bx, by + 18, 545, by + 26], radius=4, fill=(237, 236, 230))
        # Fill bar
        bar_w = int((545 - bx) * (score / 100.0))
        draw.rounded_rectangle([bx, by + 18, bx + bar_w, by + 26], radius=4, fill=color)
        by += 44
        
    # Recommendations & Keywords bottom section
    draw.line([40, 280, 560, 280], fill=(237, 236, 230), width=1)
    draw.text((40, 295), "الكلمات المفتاحية الموصى بإضافتها لزيادة التوافق:", fill=(23, 29, 27), font=get_font(13, "bold"))
    
    chips = ["+ إدارة المشاريع", "+ تحليل البيانات", "+ Agile / Scrum", "+ قيادة فرق العمل"]
    chip_x = 40
    for chip in chips:
        w = len(chip) * 9 + 20
        draw.rounded_rectangle([chip_x, 325, chip_x + w, 355], radius=15, fill=(215, 240, 229), outline=(46, 125, 91), width=1)
        draw.text((chip_x + w // 2, 340), chip, fill=(37, 106, 77), font=get_font(11, "bold"), anchor="mm")
        chip_x += w + 12
        
    draw.text((300, 392), "✓ جاهز للتقديم في كبرى الشركات وجهات التوظيف في السعودية", fill=(0, 106, 96), font=get_font(12, "semi"), anchor="mm")
    
    save_dual(img, "ats-scanner-mockup.webp")

create_ats_scanner_mockup()

# 6. CV Builder Mockup (600x440)
print("Generating CV Builder Mockup...")
def create_cv_builder_mockup():
    img = Image.new("RGB", (600, 440), (250, 247, 242))
    draw = ImageDraw.Draw(img)
    
    # Outer Container
    draw.rounded_rectangle([20, 20, 580, 420], radius=16, fill=(255, 255, 255), outline=(211, 227, 223), width=2)
    
    # Stepper Header
    draw.text((300, 45), "منشئ السيرة الذاتية الذكي · الخطوات المعتمدة", fill=(23, 29, 27), font=get_font(16, "bold"), anchor="mm")
    steps = ["1. البيانات", "2. الخبرات (الحالية)", "3. التعليم", "4. المهارات"]
    for i, s in enumerate(steps):
        sx = 60 + i * 135
        active = (i == 1)
        bg = (0, 106, 96) if active else (237, 236, 230)
        fg = (255, 255, 255) if active else (84, 97, 94)
        draw.rounded_rectangle([sx, 70, sx + 115, 96], radius=13, fill=bg)
        draw.text((sx + 57, 83), s, fill=fg, font=get_font(11, "bold"), anchor="mm")
        
    # Form Mockup on Left
    draw.rounded_rectangle([40, 115, 310, 395], radius=12, fill=(242, 241, 236), outline=(211, 227, 223))
    draw.text((55, 130), "إضافة خبرة مهنية جديدة", fill=(23, 29, 27), font=get_font(13, "bold"))
    
    # Input 1: Job Title
    draw.text((55, 158), "المسمى الوظيفي:", fill=(60, 73, 71), font=get_font(11, "semi"))
    draw.rounded_rectangle([55, 178, 295, 208], radius=8, fill=(255, 255, 255), outline=(187, 201, 198))
    draw.text((68, 193), "مهندس برمجيات أول (Senior Software Engineer)", fill=(23, 29, 27), font=get_font(10, "regular"), anchor="lm")
    
    # Input 2: Company
    draw.text((55, 218), "اسم الشركة والمدينة:", fill=(60, 73, 71), font=get_font(11, "semi"))
    draw.rounded_rectangle([55, 238, 295, 268], radius=8, fill=(255, 255, 255), outline=(187, 201, 198))
    draw.text((68, 253), "شركة تقنية متقدمة · الرياض", fill=(23, 29, 27), font=get_font(10, "regular"), anchor="lm")
    
    # Input 3: BiDi Bullet points
    draw.text((55, 278), "أبرز الإنجازات والمسؤوليات (BiDi):", fill=(60, 73, 71), font=get_font(11, "semi"))
    draw.rounded_rectangle([55, 298, 295, 355], radius=8, fill=(255, 255, 255), outline=(187, 201, 198))
    draw.text((65, 315), "• تطوير خدمات REST APIs باستخدام Laravel", fill=(23, 29, 27), font=get_font(9, "regular"), anchor="lm")
    draw.text((65, 335), "• بناء واجهات Flutter تدعم BiDi والعربية", fill=(23, 29, 27), font=get_font(9, "regular"), anchor="lm")
    
    # Form Action Button
    draw.rounded_rectangle([55, 365, 295, 390], radius=8, fill=(0, 127, 115))
    draw.text((175, 377), "حفظ ومتابعة للخطوة التالية", fill=(255, 255, 255), font=get_font(10, "bold"), anchor="mm")
    
    # Live Document Preview on Right
    draw.rounded_rectangle([330, 115, 560, 395], radius=12, fill=(255, 255, 255), outline=(0, 106, 96), width=2)
    draw.text((445, 135), "معاينة السيرة الذاتية (Live)", fill=(0, 106, 96), font=get_font(12, "bold"), anchor="mm")
    draw.line([350, 155, 540, 155], fill=(237, 236, 230))
    
    # Document lines mock
    draw.text((350, 165), "أحمد محمد المهندس", fill=(23, 29, 27), font=get_font(14, "bold"))
    draw.text((350, 185), "ahmed@example.com · الرياض · +966500000000", fill=(84, 97, 94), font=get_font(9, "regular"))
    draw.line([350, 205, 540, 205], fill=(0, 106, 96), width=1)
    
    draw.text((350, 215), "الخبرات المهنية (Experience)", fill=(0, 106, 96), font=get_font(10, "bold"))
    draw.text((350, 235), "Senior Software Engineer · 2022 - الآن", fill=(23, 29, 27), font=get_font(9, "semi"))
    draw.text((350, 252), "قيادة الفريق وبناء حلول سحابية عالية الأداء", fill=(60, 73, 71), font=get_font(9, "regular"))
    
    draw.rounded_rectangle([370, 345, 520, 380], radius=8, fill=(221, 246, 243), outline=(0, 106, 96))
    draw.text((445, 362), "تصدير فوري PDF/A-3", fill=(0, 106, 96), font=get_font(11, "bold"), anchor="mm")
    
    save_dual(img, "cv-builder-mockup.webp")

create_cv_builder_mockup()

# 7. Jobs Board Preview Mockup (600x440)
print("Generating Jobs Board Preview...")
def create_jobs_preview():
    img = Image.new("RGB", (600, 440), (250, 247, 242))
    draw = ImageDraw.Draw(img)
    
    draw.rounded_rectangle([20, 20, 580, 420], radius=16, fill=(255, 255, 255), outline=(211, 227, 223), width=2)
    
    # Header
    draw.text((40, 45), "وظائف السعودية والخليج · المطابقة الذكية", fill=(23, 29, 27), font=get_font(16, "bold"))
    
    # City Filter Tabs
    tabs = ["الكل", "الرياض", "جدة", "الشرقية", "عن بُعد"]
    tx = 40
    for i, t in enumerate(tabs):
        active = (i == 0)
        bg = (0, 106, 96) if active else (242, 241, 236)
        fg = (255, 255, 255) if active else (60, 73, 71)
        w = len(t) * 12 + 20
        draw.rounded_rectangle([tx, 75, tx + w, 102], radius=13, fill=bg)
        draw.text((tx + w // 2, 88), t, fill=fg, font=get_font(11, "semi"), anchor="mm")
        tx += w + 8
        
    # Job Cards
    jobs = [
        ("مهندس برمجيات أول (Senior Software Engineer)", "شركة وطنية كبرى · الرياض", "96%", (0, 127, 115), "دوام كامل · تقنية المعلومات"),
        ("مدير مشاريع معتمد (PMP Project Manager)", "مجموعة أعمال استثمارية · جدة", "92%", (0, 127, 115), "دوام كامل · إدارة المشاريع"),
        ("أخصائي تسويق رقمي ومحتوى (Marketing Specialist)", "منصة تقنية ناشئة · عن بُعد", "88%", (201, 127, 27), "عمل مرن · تسويق وإعلام"),
    ]
    
    jy = 125
    for title, company, match, color, tags in jobs:
        draw.rounded_rectangle([40, jy, 560, jy + 82], radius=12, fill=(255, 255, 255), outline=(211, 227, 223))
        # Match Badge
        draw.rounded_rectangle([485, jy + 12, 545, jy + 38], radius=6, fill=(221, 246, 243))
        draw.text((515, jy + 25), f"مطابقة {match}", fill=color, font=get_font(10, "bold"), anchor="mm")
        
        draw.text((55, jy + 18), title, fill=(23, 29, 27), font=get_font(13, "bold"))
        draw.text((55, jy + 38), company, fill=(84, 97, 94), font=get_font(11, "regular"))
        draw.text((55, jy + 58), tags, fill=(0, 106, 96), font=get_font(10, "semi"))
        jy += 94
        
    draw.text((300, 405), "احصل على تنبيهات فورية بالوظائف المتطابقة مع سيرتك عبر تطبيق سيرتي", fill=(84, 97, 94), font=get_font(11, "regular"), anchor="mm")
    
    save_dual(img, "jobs-preview.webp")

create_jobs_preview()

# 8. Open Graph Social Banners (1200x630) - Arabic & English
print("Generating Open Graph Banners (1200x630)...")

def create_og_ar():
    img = Image.new("RGB", (1200, 630), (18, 22, 20))
    draw = ImageDraw.Draw(img)
    
    # Brand gradient background container
    draw.rectangle([0, 0, 1200, 630], fill=(14, 26, 24))
    draw.rounded_rectangle([30, 30, 1170, 600], radius=24, fill=(18, 22, 20), outline=(0, 106, 96), width=3)
    
    # Logo mark
    draw.text((600, 140), "سيرتي · Siratie", fill=(89, 218, 201), font=get_font(38, "bold"), anchor="mm")
    draw.text((600, 230), "تطبيق سيرة ذاتية متوافقة مع أنظمة ATS", fill=(255, 255, 255), font=get_font(44, "bold"), anchor="mm")
    draw.text((600, 310), "افحص توافق سيرتك الذاتية · أنشئ سيرة احترافية · استكشف وظائف السعودية والخليج", fill=(174, 184, 180), font=get_font(20, "regular"), anchor="mm")
    
    # Feature Pill Badges
    badges = ["فحص توافق ATS فوري", "قوالب معتمدة باللغتين", "مطابقة ذكية للوظائف", "تصدير معتمد PDF/A-3"]
    bx = 180
    for b in badges:
        draw.rounded_rectangle([bx, 400, bx + 190, 450], radius=25, fill=(15, 92, 84))
        draw.text((bx + 95, 425), b, fill=(255, 255, 255), font=get_font(14, "bold"), anchor="mm")
        bx += 215
        
    draw.text((600, 530), "حمّل التطبيق الآن على iOS وأندرويد | siratie.com", fill=(89, 218, 201), font=get_font(18, "semi"), anchor="mm")
    
    save_dual(img, "og-siratie-ar.webp")

def create_og_en():
    img = Image.new("RGB", (1200, 630), (18, 22, 20))
    draw = ImageDraw.Draw(img)
    
    draw.rectangle([0, 0, 1200, 630], fill=(14, 26, 24))
    draw.rounded_rectangle([30, 30, 1170, 600], radius=24, fill=(18, 22, 20), outline=(0, 106, 96), width=3)
    
    # Logo mark
    draw.text((600, 140), "Siratie", fill=(89, 218, 201), font=get_font(42, "bold"), anchor="mm")
    draw.text((600, 230), "ATS-Friendly CV Builder & Job Discovery", fill=(255, 255, 255), font=get_font(44, "bold"), anchor="mm")
    draw.text((600, 310), "Scan Resume Compatibility · Build Bilingual CVs · Explore Curated Saudi Careers", fill=(174, 184, 180), font=get_font(20, "regular"), anchor="mm")
    
    badges = ["Instant ATS Scanner", "Bilingual BiDi Builder", "Curated Saudi Jobs", "PDF/A-3 Document Export"]
    bx = 160
    for b in badges:
        draw.rounded_rectangle([bx, 400, bx + 200, 450], radius=25, fill=(15, 92, 84))
        draw.text((bx + 100, 425), b, fill=(255, 255, 255), font=get_font(14, "bold"), anchor="mm")
        bx += 225
        
    draw.text((600, 530), "Download on iOS & Android | siratie.com", fill=(89, 218, 201), font=get_font(18, "semi"), anchor="mm")
    
    save_dual(img, "og-siratie-en.webp")

create_og_ar()
create_og_en()

print("All Milestone M1 assets successfully generated!")
