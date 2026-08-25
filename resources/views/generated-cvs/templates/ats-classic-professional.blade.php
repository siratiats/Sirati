<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 30px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1f2937;
            font-size: 11.5px;
            line-height: 1.65;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .header {
            border-bottom: 2.5px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 3px;
            font-size: 25px;
            color: #111827;
            letter-spacing: -0.5px;
        }
        .subtitle {
            color: #2563eb;
            font-size: 13.5px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .contact {
            color: #4b5563;
            font-size: 10px;
            line-height: 1.5;
            margin-top: 4px;
        }
        .contact-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
        }
        .contact-separator { color: #9ca3af; padding: 0 4px; }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1, .content h2, .content h3 {
            color: #1f2937;
            line-height: 1.3;
            page-break-after: avoid;
        }
        .content h1 { font-size: 17px; margin: 12px 0 5px; }
        .content h2 {
            font-size: 14px;
            color: #1e3a8a;
            border-bottom: 1px solid #93c5fd;
            padding-bottom: 3px;
            margin: 14px 0 6px;
            text-transform: uppercase;
        }
        .content h3 { font-size: 12px; margin: 10px 0 4px; color: #1f2937; }
        .content p { margin: 0 0 6px; }
        .content ul, .content ol {
            margin: 4px 0 8px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 18px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; color: #111827; }
        .footer {
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 8.5px;
            margin-top: 16px;
            padding-top: 6px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $pdfData['name'] }}</h1>
        <div class="subtitle">{{ $pdfData['targetJobTitle'] }}</div>
        @if ($pdfData['contacts'])
            <div class="contact">
                @foreach ($pdfData['contacts'] as $contact)
                    @if (! $loop->first)<span class="contact-separator">|</span>@endif<span class="contact-item">{{ $contact }}</span>
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