<div class="absolute inset-x-0 bottom-0 z-10">
    {{-- Fade with opacity only — a translate re-triggers hover on exit and
         flickers. Hidden from hover on coarse pointers (touch has no hover
         state to drive it), but still reachable by keyboard focus so it is
         not a dead control for anyone who tabs to it. --}}
    <button type="button" wire:click="add"
            class="pointer-events-none block w-full bg-[rgba(23,19,16,0.92)] py-[15px] text-center font-sans text-[10px] uppercase tracking-[0.22em] text-ground opacity-0 transition-opacity duration-300 focus-visible:pointer-events-auto focus-visible:opacity-100 [@media(hover:hover)_and_(pointer:fine)]:group-hover:pointer-events-auto [@media(hover:hover)_and_(pointer:fine)]:group-hover:opacity-100">
        {{ __('shop.collection.quick_add') }}
    </button>
</div>
