@php
    $daysWaiting = (int) ($order->paid_at?->diffInDays(now()) ?? 0);
    $urgency = match (true) {
        $daysWaiting >= 7 => 'text-danger-600 dark:text-danger-400',
        $daysWaiting >= 3 => 'text-warning-600 dark:text-warning-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex items-baseline justify-between">
        <span class="font-mono text-sm text-gray-500">{{ $order->order_number }}</span>
        <span class="text-xs font-medium {{ $urgency }}">{{ $daysWaiting }} days</span>
    </div>

    @foreach ($order->items as $item)
        <div class="mt-3">
            <div class="font-medium text-gray-950 dark:text-white">{{ $item->product_name }}</div>
            <div class="text-sm text-gray-500">{{ $item->variant_description }}</div>

            @if (filled($item->personalization))
                @foreach ($item->personalization as $key => $value)
                    <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-950/40">
                        <div class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-500">{{ str($key)->headline() }}</div>
                        <div class="font-mono text-2xl font-bold tracking-widest text-amber-950 dark:text-amber-200">{{ $value }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach

    <div class="mt-3 text-sm text-gray-500">To {{ $order->country_code }}</div>

    <button
        type="button"
        wire:click="mountAction('{{ $action }}', { order: {{ $order->id }} })"
        class="mt-4 w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
    >
        {{ $actionLabel }}
    </button>
</div>
