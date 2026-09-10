@if (! empty($cv['show_internal_score']) && $cv['score']['total'] !== null)
    <div class="footer">
        <span class="footer-label">{{ $cv['labels']['ats_score'] }}:</span>
        <span class="footer-metric" style="direction: ltr; unicode-bidi: embed; display: inline-block;">{{ $cv['score']['total'] }}%</span>
        @if (filled($cv['score']['grade']))
            <span class="footer-separator" style="color: #9ca3af; padding: 0 4px;">·</span>
            <span class="footer-metric" style="direction: ltr; unicode-bidi: embed; display: inline-block;">{{ $cv['score']['grade'] }}</span>
        @endif
    </div>
@endif
