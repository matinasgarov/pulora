{{-- Livewire requires every component view to render exactly one root tag, so
     the badge itself is always present and toggles via display rather than
     being conditionally omitted — an empty response here throws
     RootTagMissingFromViewException. The number inside is still omitted at
     zero, so an empty cart never prints a stray "0". --}}
<span class="cart-badge absolute right-px top-[3px] {{ $count > 0 ? 'grid' : 'hidden' }} min-w-[15px] h-[15px] place-items-center rounded-full px-[3px] font-sans text-[9px] leading-none">@if ($count > 0){{ $count }}@endif</span>
