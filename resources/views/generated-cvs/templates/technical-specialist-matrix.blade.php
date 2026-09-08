<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body {
            font-family: "DejaVu Sans", monospace, sans-serif;
            color: #1e293b;
            font-size: 11px;
            line-height: 1.65;
            margin: 0;
            text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }};
        }
        .header {
            background: #111827;
            color: #ffffff;
            padding: 18px 22px;
            margin-bottom: 16px;
            border-radius: 4px;
            page-break-inside: avoid;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 26px;
            color: #ffffff;
            font-weight: 700;
        }
        .role {
            font-size: 13.5px;
            color: #38bdf8;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .meta {
            color: #94a3b8;
            font-size: 9.5px;
            line-height: 1.5;
        }
        .meta-item {
            direction: ltr;
            display: inline-block;
            unicode-bidi: embed;
            color: #cbd5e1;
        }
        .meta-separator { color: #475569; padding: 0 5px; }
        .content { direction: {{ $cv['direction'] }}; font-family: "DejaVu Sans", sans-serif; }
        .content h1, .content h2, .content h3 {
            color: #0f172a;
            page-break-after: avoid;
        }
        .content h1 { font-size: 16px; margin: 12px 0 5px; }
        .content h2 {
            font-size: 13px;
            color: #0284c7;
            border-bottom: 1.5px solid #0284c7;
            padding-bottom: 2px;
            margin: 12px 0 5px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .content h3 { font-size: 11.5px; margin: 8px 0 3px; color: #1e293b; font-weight: bold; }
        .content p { margin: 0 0 5px; }
        .content ul, .content ol {
            margin: 3px 0 7px;
            padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 18px;
            padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;
        }
        .content li { margin-bottom: 2.5px; }
        .content strong { font-weight: bold; color: #0369a1; }
        .footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8.5px;
            margin-top: 14px;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $cv['candidate']['name'] }}</h1>
        <div class="role">{{ $cv['candidate']['target_job_title'] }}</div>
        @if ($cv['candidate']['contacts'])
            <div class="meta">
                @foreach ($cv['candidate']['contacts'] as $contact)
                    @if (! $loop->first)<span class="meta-separator">//</span>@endif<span class="meta-item">{{ $contact }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="content">
        @if (! empty($cv['structured_sections']))
            @include('generated-cvs.templates._sections')
        @else
            {!! $cv['contentHtml'] !!}
        @endif
    </div>

    @include('generated-cvs.templates._watermark')
    @include('generated-cvs.templates._footer')
</body>
</html>