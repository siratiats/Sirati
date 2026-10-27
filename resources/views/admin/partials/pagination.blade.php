@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="pager" aria-label="Pagination">
        <span>Showing {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }} of {{ number_format($paginator->total()) }}</span>
        @if ($paginator->hasPages())
            <span class="pager-links">
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true">‹ Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
                @endif
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
                @else
                    <span aria-disabled="true">Next ›</span>
                @endif
            </span>
        @endif
    </nav>
@endif
