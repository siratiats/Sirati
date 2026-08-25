<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #0f172a;
            font-size: 11.5px;
            line-height: 1.7;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .top-bar {
            height: 6px;
            background: #0ea5e9;
            margin-bottom: 14px;
            border-radius: 3px;
        }
        .header {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 26px;
            color: #0369a1;
        }
        .role {
            font-size: 13.5px;
            color: #0284c7;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .contact-box {
            color: #475569;
            font-size: 10px;
            line-height: 1.5;
        }
        .contact-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
            background: #ffffff;
            border: 1px solid #e0f2fe;
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 3px;
        }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1, .content h2, .content h3 {
            color: #0f172a;
            line-height: 1.35;
            page-break-after: avoid;
        }
        .content h1 { font-size: 17px; margin: 12px 0 6px; }
        .content h2 {
            font-size: 13.5px;
            color: #0369a1;
            background: #e0f2fe;
            padding: 4px 10px;
            border-radius: 4px;
            border-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 4px solid #0284c7;
            margin: 14px 0 6px;
        }
        .content h3 { font-size: 12px; margin: 10px 0 4px; color: #0284c7; }
        .content p { margin: 0 0 6px; }
        .content ul, .content ol {
            margin: 4px 0 8px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 20px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; color: #0369a1; }
        .footer {
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 8.5px;
            margin-top: 16px;
            padding-top: 6px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="top-bar"></div>
    <div class="header">
        <h1>{{ $pdfData['name'] }}</h1>
        <div class="role">{{ $pdfData['targetJobTitle'] }}</div>
        @if ($pdfData['contacts'])
            <div class="contact-box">
                @foreach ($pdfData['contacts'] as $contact)
                    <span class="contact-item">{{ $contact }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="content">{!! $pdfData['contentHtml'] !!}</div>

    @if ($cv['score']['total'] !== null)
        <div class="footer">
            {{ $cv['labels']['ats_score'] }}: {{ $cv['score']['total'] }}%
            @if (filled($cv['score']['grade'])) · {{ $cv['score']['grade'] }} @endif
        </div>
    @endif
</body>
</html>