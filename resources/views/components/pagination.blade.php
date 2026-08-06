@if($paginator->hasPages())
<div class="pagination">
    <span>{{ __('ui.showing') }} {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} {{ __('ui.of') }} {{ $paginator->total() }}</span>
    <div class="pager-links">
        @if($paginator->onFirstPage()) <span class="disabled">←</span> @else <a href="{{ $paginator->previousPageUrl() }}">←</a> @endif
        <span>{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}">→</a> @else <span class="disabled">→</span> @endif
    </div>
</div>
@endif
