<x-layouts.storefront title="Wallet Images" :live-cart="false">
    <section class="px-4 py-12 sm:px-11">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-rule pb-5">
            <div>
                <p class="font-sans text-[11px] uppercase tracking-[0.2em] text-accent">Wallet Images</p>
                <h1 class="mt-2 font-display text-[32px] leading-tight text-ink sm:text-[48px]">Grouped product gallery</h1>
            </div>
            <p class="font-sans text-[11px] uppercase tracking-[0.16em] text-muted">
                {{ $groups->count() }} products / {{ $groups->flatten(1)->count() }} images
            </p>
        </div>

        <div class="mt-10 space-y-16">
            @foreach ($groups as $letter => $images)
                <section>
                    <div class="mb-5 flex items-center gap-4">
                        <h2 class="font-display text-[28px] uppercase text-ink">{{ $letter }}</h2>
                        <span class="h-px flex-1 bg-rule"></span>
                        <span class="font-sans text-[11px] uppercase tracking-[0.16em] text-muted">{{ $images->count() }} angles</span>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($images as $image)
                            <a href="{{ $image['url'] }}" target="_blank" class="group block">
                                <div class="aspect-square overflow-hidden bg-white">
                                    <img
                                        src="{{ $image['url'] }}"
                                        alt="{{ strtoupper($image['name']) }}"
                                        class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]"
                                        loading="lazy"
                                    >
                                </div>
                                <p class="mt-2 font-sans text-[11px] uppercase tracking-[0.16em] text-muted">{{ $image['name'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </section>
</x-layouts.storefront>
