@php
    $s = $cv['style'] ?? config('cv_templates.style_defaults');
    $rtl = $cv['direction'] === 'rtl';
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    // Letter-spacing and uppercase break Arabic shaping, so they apply to LTR only.
    $spaced = ! $rtl;
    $bulletLines = static function (?string $text): array {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text) ?: []), fn ($l) => $l !== ''));
        $bullets = array_values(array_filter($lines, fn ($l) => preg_match('/^[-*•]\s+/u', $l) === 1));

        return count($bullets) === count($lines) && $lines !== []
            ? array_map(fn ($l) => preg_replace('/^[-*•]\s+/u', '', $l), $lines)
            : [];
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $cv['language'] }}" dir="{{ $cv['direction'] }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 14mm 12mm 14mm; }
        body {
            {{-- DejaVu keeps Arabic text extractable for ATS parsers; FreeSans is closest to the Arial-style layout for English. --}}
            font-family: {!! $rtl ? '"DejaVu Sans", sans-serif' : 'freesans, sans-serif' !!};
            color: {{ $s['text_color'] }};
            font-size: {{ $s['font_size'] }}pt;
            line-height: 1.3;
            {{-- Keep technical identifiers intact; a layout break at an internal
                 hyphen otherwise becomes an inserted space during extraction. --}}
            hyphens: none;
            margin: 0;
            text-align: {{ $start }};
        }
        .header { text-align: center; padding-bottom: 5pt; border-bottom: 2pt solid {{ $s['rule_color'] }}; margin-bottom: 8pt; page-break-inside: avoid; }
        .photo { width: 24mm; height: 24mm; border-radius: 12mm; margin: 0 auto 4pt; display: block; }
        h1.name {
            margin: 0;
            font-size: {{ $s['name_size'] }}pt;
            font-weight: bold;
            color: {{ $s['name_color'] }};
            @if ($spaced) letter-spacing: 1.5pt; @endif
            @if ($spaced && $s['uppercase_name']) text-transform: uppercase; @endif
        }
        .headline { font-weight: bold; color: #333333; font-size: {{ $s['font_size'] }}pt; margin-top: 3pt; }
        .contact { color: #333333; font-size: {{ $s['font_size'] - 0.5 }}pt; margin-top: 2pt; }
        .contact-item { direction: ltr; unicode-bidi: embed; }
        .contact-separator { color: #555555; }
        .content { direction: {{ $cv['direction'] }}; }
        h2.section-title, .content h2 {
            margin: 9pt 0 4pt;
            padding-bottom: 1.5pt;
            font-size: {{ $s['section_title_size'] }}pt;
            font-weight: bold;
            color: {{ $s['heading_color'] }};
            border-bottom: 0.75pt solid {{ $s['divider_color'] }};
            page-break-after: avoid;
            @if ($spaced) letter-spacing: 1.2pt; @endif
            @if ($spaced && $s['uppercase_section_titles']) text-transform: uppercase; @endif
        }
        p { margin: 0 0 3pt; }
        .content h3 { margin: 5pt 0 1pt; font-size: {{ $s['font_size'] }}pt; font-weight: bold; color: {{ $s['accent_color'] }}; page-break-after: avoid; }
        .accent { color: {{ $s['accent_color'] }}; font-weight: bold; }
        .muted { color: {{ $s['muted_color'] }}; }
        .pipe { color: #333333; }
        .row { width: 100%; border-collapse: collapse; }
        .row td { padding: 0; vertical-align: top; }
        .row td.date { text-align: {{ $end }}; white-space: nowrap; font-weight: bold; color: #333333; direction: ltr; unicode-bidi: embed; padding-{{ $start }}: 8pt; }
        .entry { margin-top: 5pt; page-break-inside: avoid; }
        .entry-company { font-weight: bold; }
        .entry-desc { @if (! $rtl) font-style: italic; @endif color: {{ $s['muted_color'] }}; margin: 0.5pt 0 1pt; }
        ul { margin: 1pt 0 0; padding-{{ $start }}: 14pt; padding-{{ $end }}: 0; }
        li { margin-bottom: 1.5pt; }
        .skill-group { margin-bottom: 3pt; }
        .compact li { margin-bottom: 0.5pt; }
        .footer { border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 8pt; margin-top: 12pt; padding-top: 4pt; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        @if (! empty($cv['candidate']['photo']))
            <img class="photo" src="{{ $cv['candidate']['photo'] }}" alt="">
        @endif
        <h1 class="name">{{ $cv['candidate']['name'] }}</h1>
        @if (filled($cv['candidate']['target_job_title']))
            <div class="headline">{{ $cv['candidate']['target_job_title'] }}</div>
        @endif
        @if ($cv['candidate']['contacts'])
            <div class="contact">
                @foreach ($cv['candidate']['contacts'] as $contact)
                    @if (! $loop->first)<span class="contact-separator">{{ $s['show_contact_separator'] ? '•' : '|' }}&nbsp;</span>@endif<span class="contact-item">{{ $contact }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="content">
        @if (! empty($cv['structured_sections']))
            @foreach ($cv['structured_sections'] as $section)
                @php($key = $section['key'] ?? '')
                <div class="section-block">
                    <h2 class="section-title">{{ $section['title'] }}</h2>

                    @if ($section['type'] === 'text')
                        @php($lines = $bulletLines($section['content'] ?? ''))
                        @if ($lines)
                            <ul class="compact">@foreach ($lines as $line)<li>{{ $line }}</li>@endforeach</ul>
                        @else
                            <p>{{ $section['content'] }}</p>
                        @endif

                    @elseif ($section['type'] === 'tags')
                        @if (! empty($section['groups']))
                            @foreach ($section['groups'] as $group)
                                <div class="skill-group">
                                    @if (filled($group['category']))<span class="accent">{{ $group['category'] }}</span><span class="pipe accent">&nbsp;| </span>@endif{{ implode(', ', $group['items']) }}
                                </div>
                            @endforeach
                        @else
                            <p>{{ ! empty($section['items']) ? implode(', ', $section['items']) : $section['text'] }}</p>
                        @endif

                    @elseif ($key === 'languages')
                        <p>
                            @foreach ($section['entries'] as $entry)
                                @if (! $loop->first)<span class="pipe">| </span>@endif{{ $entry['title'] }}@if (filled($entry['subtitle'] ?? null)): {{ $entry['subtitle'] }}@endif
                            @endforeach
                        </p>

                    @elseif ($key === 'education')
                        @foreach ($section['entries'] as $entry)
                            <table class="row" style="margin-bottom: 2pt;">
                                <tr>
                                    <td>
                                        <strong>{{ $entry['title'] }}</strong>
                                        @foreach (array_filter([$entry['subtitle'] ?? null, $entry['description'] ?? null], 'filled') as $part)
                                            <span class="pipe">| </span><span>{{ $part }}</span>
                                        @endforeach
                                    </td>
                                    @if (filled($entry['date_range'] ?? null))
                                        <td class="date">{{ $entry['date_range'] }}</td>
                                    @endif
                                </tr>
                            </table>
                        @endforeach

                    @elseif ($key === 'certifications')
                        <ul class="compact">
                            @foreach ($section['entries'] as $entry)
                                <li>
                                    <strong>{{ $entry['title'] }}</strong>
                                    @foreach (array_filter([$entry['subtitle'] ?? null, $entry['date_range'] ?? null, $entry['description'] ?? null], 'filled') as $part)
                                        <span class="pipe">| </span><span>{{ $part }}</span>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>

                    @elseif ($section['type'] === 'entries')
                        @foreach ($section['entries'] as $entry)
                            <div class="entry">
                                <table class="row">
                                    <tr>
                                        <td class="accent">{{ $entry['title'] }}</td>
                                        @if (filled($entry['date_range'] ?? null))
                                            <td class="date">{{ $entry['date_range'] }}</td>
                                        @endif
                                    </tr>
                                </table>
                                @if (filled($entry['subtitle'] ?? null) || filled($entry['location'] ?? null))
                                    <div class="entry-company">
                                        {{ $entry['subtitle'] ?? '' }}@if (filled($entry['subtitle'] ?? null) && filled($entry['location'] ?? null))<span class="pipe">&nbsp;| </span>@endif{{ $entry['location'] ?? '' }}
                                    </div>
                                @endif
                                @if (filled($entry['description'] ?? null))
                                    <div class="entry-desc">{{ $entry['description'] }}</div>
                                @endif
                                @if (! empty($entry['bullets']))
                                    <ul>@foreach ($entry['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @else
            {!! $cv['contentHtml'] !!}
        @endif
    </div>

    @include('generated-cvs.templates._watermark')
    @include('generated-cvs.templates._footer')
</body>
</html>
