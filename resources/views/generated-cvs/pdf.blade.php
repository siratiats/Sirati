<!DOCTYPE html>
<html lang="{{ $generatedCv->language === 'ar' ? 'ar' : 'en' }}" dir="{{ $generatedCv->language === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.7;
            margin: 28px;
            text-align: {{ $generatedCv->language === 'ar' ? 'right' : 'left' }};
        }

        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 26px;
        }

        .subtitle {
            color: #374151;
            font-size: 14px;
        }

        .content {
            white-space: pre-wrap;
            direction: {{ $generatedCv->language === 'ar' ? 'rtl' : 'ltr' }};
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $pdfData['name'] }}</h1>
        <div class="subtitle">{{ $pdfData['targetJobTitle'] }}</div>
    </div>

    <div class="content">{{ $pdfData['content'] }}</div>
</body>
</html>
