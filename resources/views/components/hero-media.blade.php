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

    {{-- Top scrim. With the header engraved into the picture — no background,
         no blur — this is the only thing holding the nav off the photograph.
         Shallow and weighted to the very top, so it darkens the band the bar
         occupies without reading as a band itself. It also has to work for
         whatever photograph is dropped in next, not just this one. --}}
    <div class="absolute inset-x-0 top-0 h-[32%] bg-gradient-to-b from-bark/65 via-bark/25 to-transparent" aria-hidden="true"></div>

    {{-- Scrim, dark rather than light. The contrast tests pin tokens against
         tokens and a photograph is neither, so the copy needs a known surface
         under it. Fading to bark rather than to the ground means the picture
         keeps its own darkness instead of being bleached pale at the bottom to
         accommodate near-black text — and the copy goes light to match. --}}
    <div class="absolute inset-x-0 bottom-0 h-[68%] bg-gradient-to-t from-bark via-bark/80 to-transparent" aria-hidden="true"></div>
@else
    <x-placeholder-frame :caption="__('shop.placeholder.hero')" class="h-full w-full" />
@endif
