<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #111827;
            font-size: 12px;
            line-height: 1.7;
            margin: 28px;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        h1 { margin: 0 0 4px; font-size: 26px; }
        .subtitle { color: #374151; font-size: 14px; }
        .contact { color: #4b5563; font-size: 10px; margin-top: 7px; }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1,
        .content h2,
        .content h3 {
            color: #1f2937;
            line-height: 1.35;
            margin: 14px 0 6px;
            page-break-after: avoid;
        }
        .content h1 { font-size: 19px; }
        .content h2 {
            border-bottom: 1px solid #d1d5db;
            font-size: 16px;
            padding-bottom: 3px;
        }
        .content h3 { font-size: 13px; }
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
        .content td { border: 1px solid #d1d5db; padding: 5px; }
        .footer {
            border-top: 1px solid #d1d5db;
            color: #6b7280;
            font-size: 9px;
            margin-top: 18px;
            padding-top: 7px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $pdfData['name'] }}</h1>
        <div class="subtitle">{{ $pdfData['targetJobTitle'] }}</div>
        @if ($pdfData['contacts'])
            <div class="contact">{{ implode(' · ', $pdfData['contacts']) }}</div>
        @endif
    </div>

    <div class="content">{!! $pdfData['contentHtml'] !!}</div>

    @if ($cv['score']['total'] !== null)
        <div class="footer">
            {{ $cv['labels']['ats_score'] }}:
            {{ $cv['score']['total'] }}
            @if (filled($cv['score']['grade'])) · {{ $cv['score']['grade'] }} @endif
        </div>
    @endif
</body>
</html>
