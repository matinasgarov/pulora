<x-filament-panels::page>
    <div class="flex justify-end">
        <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="loadQueue">
            Refresh
        </x-filament::button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">To make ({{ $toMake->count() }})</h2>

            <div class="space-y-4">
                @foreach ($toMake as $order)
                    @include('filament.pages.workshop-card', ['order' => $order, 'action' => 'start_production', 'actionLabel' => 'Start making'])
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">In production ({{ $inProduction->count() }})</h2>

            <div class="space-y-4">
                @foreach ($inProduction as $order)
                    @include('filament.pages.workshop-card', ['order' => $order, 'action' => 'mark_ready', 'actionLabel' => 'Mark made'])
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Ready to post ({{ $readyToPost->count() }})</h2>

            <div class="space-y-4">
                @foreach ($readyToPost as $order)
                    @include('filament.pages.workshop-card', ['order' => $order, 'action' => 'ship', 'actionLabel' => 'Mark posted'])
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ $awaitingPayment }}</div>
            <div class="text-sm text-gray-500">awaiting payment</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ $overdue }}</div>
            <div class="text-sm text-gray-500">waiting over 7 days</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="text-2xl font-bold text-gray-950 dark:text-white">{{ \App\Domain\Money::format($revenueThisMonthMinor) }}</div>
            <div class="text-sm text-gray-500">this month</div>
        </div>
    </div>
</x-filament-panels::page>
