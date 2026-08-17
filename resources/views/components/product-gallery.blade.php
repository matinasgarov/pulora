@props(['images', 'name'])

{{-- One frame in view at a time, arrows over it, thumbnails under it.

     The frame is 4/5 because that is the ratio ProductImageNormalizer writes,
     so `object-cover` crops nothing and no letterbox bands appear beside a
     product. The white frame background matches the normalized sweep, so the
     photograph reaches the frame edge without a seam.

     Without JavaScript this is still a horizontal scroller — it swipes on
     touch, scrolls on a trackpad, and the thumbnails are ordinary anchors that
     scroll the track to their frame. The arrows and the zoom are the
     enhancement; the arrows stay hidden until the script unhides them, so
     nothing dead is ever on screen. --}}
@php
    $galleryId = 'gallery-'.$name;
@endphp

<div data-gallery class="relative" role="group" aria-roledescription="carousel" aria-label="{{ __('shop.product.gallery.label') }}">
    <div data-gallery-track
         class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($images as $index => $image)
            <div id="{{ $galleryId }}-{{ $index }}"
                 data-gallery-frame
                 class="relative aspect-[4/5] w-full shrink-0 snap-center overflow-hidden bg-white lg:aspect-auto lg:h-[min(74vh,760px)]">
                {{-- `contain`, so a short frame never crops the product. The
                     letterboxing it leaves is invisible: the frame is white and
                     so is the normalized sweep. --}}
                <img src="{{ asset('storage/'.$image->path) }}"
                     alt="{{ $image->alt_text }}"
                     data-gallery-image
                     loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                     class="h-full w-full object-contain transition-transform duration-200 ease-out">
            </div>
        @endforeach
    </div>

    @if ($images->count() > 1)
        {{-- Hidden until the script takes over, so they never render inert. --}}
        <button type="button" data-gallery-prev hidden
                class="absolute top-1/2 left-3 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-rule bg-ground/85 text-ink backdrop-blur-[6px] hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink [&:not([hidden])]:flex">
            <span class="sr-only">{{ __('shop.product.gallery.previous') }}</span>
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2" class="h-5 w-5" aria-hidden="true">
                <path d="M12.5 4 6.5 10l6 6" stroke-linecap="square"/>
            </svg>
        </button>

        <button type="button" data-gallery-next hidden
                class="absolute top-1/2 right-3 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-rule bg-ground/85 text-ink backdrop-blur-[6px] hover:border-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink [&:not([hidden])]:flex">
            <span class="sr-only">{{ __('shop.product.gallery.next') }}</span>
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.2" class="h-5 w-5" aria-hidden="true">
                <path d="M7.5 4l6 6-6 6" stroke-linecap="square"/>
            </svg>
        </button>

        <div class="mt-[10px] flex gap-[10px]">
            @foreach ($images as $index => $image)
                <a href="#{{ $galleryId }}-{{ $index }}"
                   data-gallery-thumb="{{ $index }}"
                   aria-label="{{ __('shop.product.gallery.go_to', ['number' => $index + 1]) }}"
                   class="aspect-[4/5] w-[68px] shrink-0 overflow-hidden border border-transparent bg-white aria-[current=true]:border-ink">
                    <img src="{{ asset('storage/'.$image->path) }}" alt="" class="h-full w-full object-cover">
                </a>
            @endforeach
        </div>
    @endif
</div>
