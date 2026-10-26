@php
    $s = $cv['style'] ?? config('cv_templates.style_defaults');
    $rtl = $cv['direction'] === 'rtl';
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    // A text block up to about six lines may move whole to keep its heading.
    $keepChars = 600;
    // Letter-spacing and uppercase break Arabic shaping, so they apply to LTR only.
    $spaced = ! $rtl;
    // Field separators as in the reference layout: "Company  |  City".
    $sep = '&nbsp;&nbsp;|&nbsp; ';
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
        {{-- Measurements follow the reference CV (Arial-metric sans, A4, 12.4mm sides). --}}
        @page { margin: 12mm 12.4mm 12mm 12.4mm; }
        body {
            {{-- DejaVu keeps Arabic text extractable for ATS parsers; Liberation Sans is Arial-metric for English. --}}
            font-family: {!! $rtl ? '"DejaVu Sans", sans-serif' : 'liberationsans, sans-serif' !!};
            color: {{ $s['text_color'] }};
            font-size: {{ $s['font_size'] }}pt;
            line-height: 1.25;
            {{-- Keep technical identifiers intact; a layout break at an internal
                 hyphen otherwise becomes an inserted space during extraction. --}}
            hyphens: none;
            margin: 0;
            text-align: {{ $start }};
        }
        .header { text-align: center; padding-bottom: 5pt; border-bottom: 1.5pt solid {{ $s['rule_color'] }}; margin-bottom: 3pt; page-break-inside: avoid; }
        .photo { width: 24mm; height: 24mm; border-radius: 12mm; margin: 0 auto 4pt; display: block; }
        h1.name {
            margin: 0;
            font-size: {{ $s['name_size'] }}pt;
            font-weight: bold;
            color: {{ $s['name_color'] }};
            line-height: 1.2;
            @if ($spaced) letter-spacing: 1.2pt; @endif
            @if ($spaced && $s['uppercase_name']) text-transform: uppercase; @endif
        }
        .headline { font-weight: bold; color: {{ $s['muted_color'] }}; font-size: {{ $s['font_size'] - 0.5 }}pt; margin-top: 2pt; }
        .contact { color: {{ $s['muted_color'] }}; font-size: {{ $s['font_size'] - 0.5 }}pt; margin-top: 2pt; }
        .contact-item { direction: ltr; unicode-bidi: embed; }
        .content { direction: {{ $cv['direction'] }}; }
        h2.section-title, .content h2 {
            margin: 5.7pt 0 3.4pt;
            padding-bottom: 1.4pt;
            font-size: {{ $s['section_title_size'] }}pt;
            font-weight: bold;
            color: {{ $s['heading_color'] }};
            border-bottom: 0.75pt solid {{ $s['divider_color'] }};
            page-break-after: avoid;
            @if ($spaced) letter-spacing: 1pt; @endif
            @if ($spaced && $s['uppercase_section_titles']) text-transform: uppercase; @endif
        }
        p { margin: 0 0 3pt; }
        .text-block { line-height: 1.14; }
        .content h3 { margin: 5pt 0 1pt; font-size: {{ $s['font_size'] }}pt; font-weight: bold; color: {{ $s['accent_color'] }}; page-break-after: avoid; }
        .accent { color: {{ $s['accent_color'] }}; font-weight: bold; }
        .entry-title { font-size: {{ $s['section_title_size'] }}pt; }
        .muted, .detail { color: {{ $s['muted_color'] }}; }
        .row { width: 100%; border-collapse: collapse; }
        .row td { padding: 0; vertical-align: top; }
        .row td.date { text-align: {{ $end }}; white-space: nowrap; font-weight: bold; font-size: {{ $s['font_size'] - 0.5 }}pt; color: {{ $s['muted_color'] }}; direction: ltr; unicode-bidi: embed; padding-{{ $start }}: 8pt; }
        .entry { margin-top: 4.6pt; }
        .edu-row { margin-bottom: 2pt; }
        .keep, .entry-head { page-break-inside: avoid; }
        .entry-company { font-weight: bold; }
        .entry-desc { @if (! $rtl) font-style: italic; @endif font-size: {{ $s['font_size'] - 0.5 }}pt; color: {{ $s['muted_color'] }}; margin: 0 0 1pt; }
        ul { margin: 2.2pt 0 0; padding-{{ $start }}: 18pt; padding-{{ $end }}: 0; }
        li { margin-bottom: 2.7pt; }
        .skill-group { margin-bottom: 3.5pt; }
        .compact li { margin-bottom: 1.2pt; }
        .languages { font-size: {{ $s['font_size'] - 1 }}pt; color: {{ $s['muted_color'] }}; }
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
                    @if (! $loop->first)<span>&nbsp;&nbsp;&nbsp;{{ $s['show_contact_separator'] ? '•' : '|' }}&nbsp;&nbsp; </span>@endif<span class="contact-item">{{ $contact }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="content">
        @if (! empty($cv['structured_sections']))
            @foreach ($cv['structured_sections'] as $section)
                @php($key = $section['key'] ?? '')
                <div class="section-block">
                    @php($heading = '<h2 class="section-title">'.e($section['title']).'</h2>')

                    {{-- Only the heading and the first item are kept together; a whole
                         block is kept only when it is short, so nothing long jumps
                         to the next page and leaves a gap. --}}
                    @if ($section['type'] === 'text')
                        @php($lines = $bulletLines($section['content'] ?? ''))
                        @if ($lines)
                            <div class="keep">{!! $heading !!}<ul class="compact"><li>{{ $lines[0] }}</li></ul></div>
                            @if (count($lines) > 1)
                                <ul class="compact">@foreach (array_slice($lines, 1) as $line)<li>{{ $line }}</li>@endforeach</ul>
                            @endif
                        @elseif (mb_strlen((string) $section['content']) <= $keepChars)
                            <div class="keep">{!! $heading !!}<p class="text-block">{!! nl2br(e($section['content']), false) !!}</p></div>
                        @else
                            {!! $heading !!}
                            <p class="text-block">{!! nl2br(e($section['content']), false) !!}</p>
                        @endif

                    @elseif ($section['type'] === 'tags')
                        @if (! empty($section['groups']))
                            @foreach ($section['groups'] as $group)
                                @if ($loop->first)<div class="keep">{!! $heading !!}@endif
                                <div class="skill-group">
                                    @if (filled($group['category']))<span class="accent">{{ $group['category'] }}{!! $sep !!}</span>@endif{{ implode(', ', $group['items']) }}
                                </div>
                                @if ($loop->first)</div>@endif
                            @endforeach
                        @else
                            @php($tagText = ! empty($section['items']) ? implode(', ', $section['items']) : (string) $section['text'])
                            @if (mb_strlen($tagText) <= $keepChars)
                                <div class="keep">{!! $heading !!}<p>{{ $tagText }}</p></div>
                            @else
                                {!! $heading !!}
                                <p>{{ $tagText }}</p>
                            @endif
                        @endif

                    @elseif ($key === 'languages')
                        <div class="keep">
                            {!! $heading !!}
                            <p class="languages">{!! collect($section['entries'])->map(fn (array $entry): string => e($entry['title']).(filled($entry['subtitle'] ?? null) ? ': '.e($entry['subtitle']) : ''))->implode($sep) !!}</p>
                        </div>

                    @elseif ($key === 'education')
                        @foreach ($section['entries'] as $entry)
                            <div class="entry-head">
                                @if ($loop->first){!! $heading !!}@endif
                                <table class="row edu-row">
                                    <tr>
                                        <td>
                                            {{-- Degree in bold, then institution and each detail line in grey. --}}
                                            <strong>{{ $entry['title'] }}</strong>@foreach (array_filter([$entry['subtitle'] ?? null, ...(preg_split('/\R/u', (string) ($entry['description'] ?? '')) ?: [])], 'filled') as $part)<span class="detail">{!! $sep !!}{{ trim($part) }}</span>@endforeach
                                        </td>
                                        @if (filled($entry['date_range'] ?? null))
                                            <td class="date">{{ $entry['date_range'] }}</td>
                                        @endif
                                    </tr>
                                </table>
                            </div>
                        @endforeach

                    @elseif ($key === 'certifications')
                        @foreach ($section['entries'] as $entry)
                            @if ($loop->first)<div class="entry-head">{!! $heading !!}@endif
                            <ul class="compact"@if (! $loop->first) style="margin-top: 0"@endif>
                                <li>
                                    <strong>{{ $entry['title'] }}</strong>@foreach (array_filter([$entry['subtitle'] ?? null, $entry['date_range'] ?? null, $entry['description'] ?? null], 'filled') as $part)<span class="detail">{!! $sep !!}{{ $part }}</span>@endforeach
                                </li>
                            </ul>
                            @if ($loop->first)</div>@endif
                        @endforeach

                    @elseif ($section['type'] === 'entries')
                        @foreach ($section['entries'] as $entry)
                            {{-- The first entry follows its section title directly. --}}
                            <div class="entry"@if ($loop->first) style="margin-top: 0"@endif>
                                {{-- The job header never parts from its section title (first
                                     entry) or from the start of its body; the body may split. --}}
                                <div class="entry-head">
                                    @if ($loop->first){!! $heading !!}@endif
                                    <table class="row"@if ($loop->first) style="margin-top: 4pt"@endif>
                                        <tr>
                                            <td class="accent entry-title">{{ $entry['title'] }}</td>
                                            @if (filled($entry['date_range'] ?? null))
                                                <td class="date">{{ $entry['date_range'] }}</td>
                                            @endif
                                        </tr>
                                    </table>
                                    @if (filled($entry['subtitle'] ?? null) || filled($entry['location'] ?? null))
                                        <div class="entry-company">
                                            {{ $entry['subtitle'] ?? '' }}@if (filled($entry['subtitle'] ?? null) && filled($entry['location'] ?? null)){!! $sep !!}@endif{{ $entry['location'] ?? '' }}
                                        </div>
                                    @endif
                                    @if (filled($entry['description'] ?? null))
                                        <div class="entry-desc">{!! nl2br(e($entry['description']), false) !!}</div>
                                    @endif
                                    @if (! empty($entry['bullets']))
                                        <ul><li>{{ $entry['bullets'][0] }}</li></ul>
                                    @endif
                                </div>
                                @if (count($entry['bullets'] ?? []) > 1)
                                    <ul style="margin-top: 0">@foreach (array_slice($entry['bullets'], 1) as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
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
