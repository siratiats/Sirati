@if (! empty($cv['structured_sections']))
    @foreach ($cv['structured_sections'] as $section)
        <div class="section-block" style="margin-bottom: 14px;">
            <h2 class="section-title" style="page-break-after: avoid;">{{ $section['title'] }}</h2>
            <div class="section-body">
                @if ($section['type'] === 'text')
                    <p style="margin: 0 0 6px; line-height: 1.6;">{{ $section['content'] }}</p>
                @elseif ($section['type'] === 'entries')
                    @foreach ($section['entries'] as $entry)
                        <div class="entry-item" style="page-break-inside: avoid; margin-bottom: 8px;">
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                                <tr>
                                    <td style="text-align: {{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}; vertical-align: top; padding: 0;">
                                        <h3 style="margin: 0; font-size: 12px; font-weight: bold; color: inherit;">{{ $entry['title'] }}</h3>
                                    </td>
                                    @if (! empty($entry['date_range']))
                                        <td style="text-align: {{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}; vertical-align: top; white-space: nowrap; font-size: 10px; color: #6b7280; direction: ltr; unicode-bidi: embed; padding: 0;">
                                            {{ $entry['date_range'] }}
                                        </td>
                                    @endif
                                </tr>
                            </table>
                            @if (! empty($entry['subtitle']) || ! empty($entry['location']))
                                <div style="font-size: 11px; color: #4b5563; font-weight: 500; margin-bottom: 3px;">
                                    {{ $entry['subtitle'] ?? '' }}
                                    @if (! empty($entry['subtitle']) && ! empty($entry['location'])) · @endif
                                    {{ $entry['location'] ?? '' }}
                                </div>
                            @endif
                            @if (! empty($entry['description']))
                                <p style="margin: 2px 0 4px; font-size: 11px; line-height: 1.5;">{{ $entry['description'] }}</p>
                            @endif
                            @if (! empty($entry['bullets']))
                                <ul style="margin: 3px 0 6px; padding-{{ $cv['direction'] === 'rtl' ? 'right' : 'left' }}: 18px; padding-{{ $cv['direction'] === 'rtl' ? 'left' : 'right' }}: 0;">
                                    @foreach ($entry['bullets'] as $bullet)
                                        <li style="margin-bottom: 2px; font-size: 11px; line-height: 1.5;">{{ $bullet }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                @elseif ($section['type'] === 'tags')
                    <div style="page-break-inside: avoid;">
                        @if (! empty($section['items']))
                            <p style="margin: 0; font-size: 11px; line-height: 1.6;">{{ implode('  ·  ', $section['items']) }}</p>
                        @else
                            <p style="margin: 0; font-size: 11px; line-height: 1.6;">{{ $section['text'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endif
