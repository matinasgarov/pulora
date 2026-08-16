@props(['caption', 'insetClass' => 'top-[28px] right-[44px]'])

{{-- Photography has not been shot yet. This deliberately does not hide the
     gap behind a blank box: the diagonal-stripe fill plus a monospace shot
     description keeps the layout real while making it obvious what is
     missing. See docs/superpowers/plans/2026-08-16-pulora-design-phase1.md,
     Task 4. --}}
<div {{ $attributes->merge(['class' => 'relative overflow-hidden']) }}
     style="background-image: repeating-linear-gradient(135deg, #EFEBE3 0 14px, #EBE6DD 14px 28px);">
    <span class="absolute max-w-[75%] text-right font-mono text-[9px] uppercase leading-snug tracking-[0.08em] text-ink-soft/80 {{ $insetClass }}">
        {{ $caption }}
    </span>
</div>
