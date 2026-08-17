@props(['path' => null, 'name' => '', 'size' => 'w-[84px]'])

{{-- The photograph beside a bag or checkout line.

     4/5 and white to match ProductImageNormalizer's canvas, so the thumbnail
     is the tile image at a smaller size rather than a differently-cropped one.
     A product with no photography yet gets an empty frame of the same size —
     the row keeps its shape either way, so a bag of two items does not have
     one line indented past the other. --}}
<div class="{{ $size }} aspect-[4/5] shrink-0 overflow-hidden border border-rule bg-white">
    @if ($path)
        <img src="{{ asset('storage/'.$path) }}" alt="{{ $name }}" class="h-full w-full object-cover" loading="lazy">
    @endif
</div>
