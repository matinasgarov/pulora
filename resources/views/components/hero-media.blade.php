@props(['poster' => null, 'videoSources' => []])

{{-- The hero's backdrop: photograph first, loop second.

     The photograph is the page's largest paint, so it loads eagerly at high
     priority and is what every visitor sees. The loop, when there is one, is
     layered over it and fades in only once it is actually playing — so a slow
     connection shows the photograph rather than a black rectangle, and a
     failure to play is indistinguishable from there being no video at all. --}}
@if ($poster)
    <img src="{{ $poster }}"
         alt="{{ __('shop.hero.poster_alt') }}"
         fetchpriority="high"
         class="absolute inset-0 h-full w-full object-cover">

    @if (count($videoSources) > 0)
        {{-- No `src`/`<source>` in the markup and no `poster` attribute: the
             sources are handed over by the script only once it has decided this
             visitor should get the video, so a phone on mobile data downloads
             none of it. The photograph underneath is already the poster. --}}
        <video data-hero-video
               data-sources="{{ json_encode($videoSources) }}"
               muted playsinline loop preload="none" tabindex="-1" aria-hidden="true"
               class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-700 ease-out motion-reduce:hidden"></video>
    @endif

    {{-- Scrim. The hero copy is `text-ink` on the near-white ground, which the
         contrast tests cover — but they test tokens against tokens, and a
         photograph is neither. This keeps the bottom of the frame close to the
         ground colour so the headline stays readable over whatever gets shot,
         without dimming the whole picture. --}}
    <div class="absolute inset-x-0 bottom-0 h-[62%] bg-gradient-to-t from-ground via-ground/75 to-transparent" aria-hidden="true"></div>
@else
    <x-placeholder-frame :caption="__('shop.placeholder.hero')" class="h-full w-full" />
@endif
