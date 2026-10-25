<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('generated_cvs.not_ready.title', [], 'ar') }}</title>
    <style>
        body { margin: 0; background: #f7f5ef; color: #1c2b26; font-family: system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif; }
        main { max-width: 520px; margin: 0 auto; padding: 48px 20px; }
        .card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
        h1 { margin: 0 0 12px; font-size: 1.25rem; color: #0b5c4b; }
        p { margin: 0 0 12px; line-height: 1.7; }
        .en { direction: ltr; text-align: left; border-top: 1px solid #e6e1d6; padding-top: 16px; margin-top: 16px; }
        .hint { color: #5b6661; font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>{{ __('generated_cvs.not_ready.heading', [], 'ar') }}</h1>
        <p>{{ $messageAr }}</p>
        <p class="hint">{{ __('generated_cvs.not_ready.hint', [], 'ar') }}</p>
        <div class="en" lang="en">
            <h1>This CV isn't ready to download yet</h1>
            <p>{{ $messageEn }}</p>
            <p class="hint">Go back to the Sirati app to finish it, then download it again.</p>
        </div>
    </div>
</main>
</body>
</html>
