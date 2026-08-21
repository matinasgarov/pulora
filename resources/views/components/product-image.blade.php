@props([
    'path',
    'alt' => '',
    'width' => \App\Support\ProductImageDerivatives::TILE,
    'loading' => 'lazy',
])

@php
    use App\Support\ProductImageDerivatives;

    $filename = basename($path);
    $directory = dirname($path);

    $jpeg = ProductImageDerivatives::name($filename, $width, 'jpg');
    $webp = ProductImageDerivatives::name($filename, $width, 'webp');

    // A product photographed through the admin panel has no derivatives, and
    // nothing regenerates them for it — so the presence of the file decides,
    // rather than assuming the seeder has run. Missing derivatives mean a
    // heavier page, never a broken image.
    $hasDerivatives = file_exists(public_path('storage/'.$directory.'/'.$jpeg))
        && file_exists(public_path('storage/'.$directory.'/'.$webp));
@endphp

@if ($hasDerivatives)
    <picture>
        <source srcset="{{ asset('storage/'.$directory.'/'.$webp) }}" type="image/webp">
        <img src="{{ asset('storage/'.$directory.'/'.$jpeg) }}"
             alt="{{ $alt }}"
             loading="{{ $loading }}"
             decoding="async"
             {{ $attributes }}>
    </picture>
@else
    <img src="{{ asset('storage/'.$path) }}"
         alt="{{ $alt }}"
         loading="{{ $loading }}"
         decoding="async"
         {{ $attributes }}>
@endif
