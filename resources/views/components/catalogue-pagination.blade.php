@props(['paginator'])

{{-- Plain links, so a page is shareable and the back button works. Renders
     nothing at all when everything fits on one page. --}}
@if ($paginator->hasPages())
    <nav class="mt-16 flex items-center justify-center gap-2 font-sans text-[11px] uppercase tracking-[0.16em]"
         aria-label="{{ __('shop.collection.pagination.label') }}">
        @if ($paginator->onFirstPage())
            <span class="flex h-11 min-w-11 items-center justify-center px-3text-muted-lightest">{{ __('shop.collection.pagination.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="flex h-11 min-w-11 items-center justify-center px-3text-muted hover:text-ink">{{ __('shop.collection.pagination.previous') }}</a>
        @endif

        @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if ($page === $paginator->currentPage())
                <span aria-current="page" class="border-b border-ink flex h-11 min-w-11 items-center justify-center px-3text-ink">{{ $page }}</span>
            @else
                <a href="{{ $url }}" class="border-b border-transparent flex h-11 min-w-11 items-center justify-center px-3text-muted hover:text-ink">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="flex h-11 min-w-11 items-center justify-center px-3text-muted hover:text-ink">{{ __('shop.collection.pagination.next') }}</a>
        @else
            <span class="flex h-11 min-w-11 items-center justify-center px-3text-muted-lightest">{{ __('shop.collection.pagination.next') }}</span>
        @endif
    </nav>
@endif
