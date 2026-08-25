<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #172554;
            font-size: 11.5px;
            line-height: 1.65;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .header {
            border-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 5px solid #dc2626;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 14px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 3px;
            font-size: 27px;
            color: #1e3a8a;
            font-weight: 800;
        }
        .role {
            font-size: 14px;
            color: #dc2626;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .contacts {
            color: #475569;
            font-size: 10px;
            line-height: 1.5;
        }
        .contact-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
        }
        .contact-sep { color: #dc2626; padding: 0 5px; font-weight: bold; }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1, .content h2, .content h3 {
            color: #1e3a8a;
            page-break-after: avoid;
        }
        .content h1 { font-size: 17px; margin: 12px 0 6px; }
        .content h2 {
            font-size: 13.5px;
            color: #dc2626;
            border-bottom: 1.5px solid #fca5a5;
            padding-bottom: 3px;
            margin: 14px 0 6px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .content h3 { font-size: 12px; margin: 10px 0 4px; color: #1e3a8a; font-weight: bold; }
        .content p { margin: 0 0 6px; }
        .content ul, .content ol {
            margin: 4px 0 8px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 20px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; color: #b91c1c; }
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
    <div class="header">
        <h1>{{ $pdfData['name'] }}</h1>
        <div class="role">{{ $pdfData['targetJobTitle'] }}</div>
        @if ($pdfData['contacts'])
            <div class="contacts">
                @foreach ($pdfData['contacts'] as $contact)
                    @if (! $loop->first)<span class="contact-sep">■</span>@endif<span class="contact-item">{{ $contact }}</span>
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