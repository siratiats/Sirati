<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            color: #0f172a;
            font-size: 11.5px;
            line-height: 1.7;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .shell {
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 16px 20px;
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 16px 18px;
            border-radius: 6px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 3px;
            font-size: 26px;
            color: #ffffff;
        }
        .role {
            font-size: 13.5px;
            color: #60a5fa;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .meta {
            color: #cbd5e1;
            font-size: 9.5px;
            line-height: 1.5;
        }
        .meta-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
            color: #e2e8f0;
        }
        .meta-separator { color: #64748b; padding: 0 5px; }
        .content { direction: {{ $cv['direction'] }}; }
        .content h1, .content h2, .content h3 {
            color: #0f172a;
            page-break-after: avoid;
        }
        .content h1 { font-size: 16.5px; margin: 12px 0 6px; }
        .content h2 {
            font-size: 13.5px;
            color: #1d4ed8;
            border-bottom: 1.5px solid #2563eb;
            padding-bottom: 3px;
            margin: 12px 0 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .content h3 { font-size: 12px; margin: 10px 0 4px; color: #1e293b; font-weight: bold; }
        .content p { margin: 0 0 6px; }
        .content ul, .content ol {
            margin: 4px 0 8px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 20px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 3px; }
        .content strong { font-weight: bold; color: #1e40af; }
        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8.5px;
            margin-top: 14px;
            padding-top: 6px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="header">
            <h1>{{ $pdfData['name'] }}</h1>
            <div class="role">{{ $pdfData['targetJobTitle'] }}</div>
            @if ($pdfData['contacts'])
                <div class="meta">
                    @foreach ($pdfData['contacts'] as $contact)
                        @if (! $loop->first)<span class="meta-separator">·</span>@endif<span class="meta-item">{{ $contact }}</span>
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
    </div>
</body>
</html>