<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.75;
            margin: 26px;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .shell { border: 1px solid #cbd5e1; padding: 22px; }
        .header {
            background: {{ $cv['template']['colors']['primary'] ?? '#1f2937' }};
            color: #ffffff;
            padding: 18px 20px;
            margin: -22px -22px 18px;
            page-break-inside: avoid;
        }
        h1 { margin: 0 0 4px; font-size: 28px; }
        .role { font-size: 14px; color: #dbeafe; }
        .meta { margin-top: 8px; color: #e2e8f0; font-size: 11px; }
        .section { margin-top: 14px; }
        .content h1,
        .content h2,
        .content h3 {
            line-height: 1.35;
            page-break-after: avoid;
        }
        .content h1 { font-size: 19px; margin: 14px 0 7px; }
        .content h2 {
            border-bottom: 1px solid {{ $cv['template']['colors']['accent'] ?? '#2563eb' }};
            color: {{ $cv['template']['colors']['accent'] ?? '#2563eb' }};
            font-size: 15px;
            margin: 14px 0 6px;
            padding-bottom: 4px;
        }
        .content h3 { font-size: 13px; margin: 11px 0 5px; }
        .content p { margin: 0 0 7px; }
        .content ul,
        .content ol {
            margin: 4px 0 9px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 22px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; }
        .content em { font-style: italic; }
        .content table { border-collapse: collapse; margin: 8px 0; width: 100%; }
        .content th,
        .content td { border: 1px solid #cbd5e1; padding: 5px; }
        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 9px;
            margin-top: 18px;
            padding-top: 7px;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="header">
            <h1>{{ $pdfData['name'] }}</h1>
            <div class="role">{{ $pdfData['targetJobTitle'] }}</div>
            @if ($pdfData['contacts'])
                <div class="meta">{{ implode(' · ', $pdfData['contacts']) }}</div>
            @endif
        </div>

        <div class="section">
            <div class="content">{!! $pdfData['contentHtml'] !!}</div>
        </div>

        @if ($cv['score']['total'] !== null)
            <div class="footer">
                {{ $cv['language'] === 'en' ? 'ATS score' : 'نتيجة ATS' }}:
                {{ $cv['score']['total'] }}
                @if (filled($cv['score']['grade'])) · {{ $cv['score']['grade'] }} @endif
            </div>
        @endif
    </div>
</body>
</html>
