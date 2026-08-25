<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #1e293b;
            font-size: 11.5px;
            line-height: 1.7;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .header {
            border-top: 3px solid #0d9488;
            border-bottom: 1px solid #cbd5e1;
            padding: 16px 0 12px;
            margin-bottom: 16px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 26px;
            color: #134e4a;
            letter-spacing: 0.5px;
        }
        .role {
            font-size: 14px;
            color: #0d9488;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .contacts {
            color: #64748b;
            font-size: 10px;
            line-height: 1.5;
        }
        .contact-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
        }
        .contact-sep { color: #0d9488; padding: 0 6px; font-weight: bold; }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1, .content h2, .content h3 {
            color: #134e4a;
            page-break-after: avoid;
        }
        .content h1 { font-size: 17px; margin: 12px 0 6px; }
        .content h2 {
            font-size: 13.5px;
            color: #0f766e;
            border-bottom: 1.5px solid #0d9488;
            padding-bottom: 3px;
            margin: 14px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .content h3 { font-size: 12px; margin: 10px 0 4px; color: #1e293b; }
        .content p { margin: 0 0 6px; }
        .content ul, .content ol {
            margin: 4px 0 8px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 20px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; color: #0f766e; }
        .footer {
            border-top: 1px solid #cbd5e1;
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
                    @if (! $loop->first)<span class="contact-sep">♦</span>@endif<span class="contact-item">{{ $contact }}</span>
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