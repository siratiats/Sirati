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

        .shell {
            border: 1px solid #cbd5e1;
            padding: 22px;
        }

        .header {
            background: {{ $cv['template']['colors']['primary'] ?? '#1f2937' }};
            color: #ffffff;
            padding: 18px 20px;
            margin: -22px -22px 18px;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 28px;
        }

        .role {
            font-size: 14px;
            color: #dbeafe;
        }

        .meta {
            margin-top: 8px;
            color: #e2e8f0;
            font-size: 11px;
        }

        .section {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        h2 {
            border-bottom: 1px solid {{ $cv['template']['colors']['accent'] ?? '#2563eb' }};
            color: {{ $cv['template']['colors']['accent'] ?? '#2563eb' }};
            font-size: 15px;
            margin: 0 0 6px;
            padding-bottom: 4px;
        }

        .content {
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="header">
            <h1>{{ $pdfData['name'] }}</h1>
            <div class="role">{{ $pdfData['targetJobTitle'] }}</div>
            <div class="meta">
                {{ $cv['candidate']['email'] }}
                @if ($cv['candidate']['phone']) | {{ $cv['candidate']['phone'] }} @endif
                @if ($cv['candidate']['location']) | {{ $cv['candidate']['location'] }} @endif
            </div>
        </div>

        <div class="section">
            <h2>{{ $cv['language'] === 'en' ? 'Generated CV' : 'السيرة الذاتية' }}</h2>
            <div class="content">{{ $pdfData['content'] }}</div>
        </div>
    </div>
</body>
</html>
