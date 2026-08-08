<?php // app/Jobs/ReleaseExpiredReservations.php

namespace App\Jobs;

use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredReservations implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->whereNotNull('reserved_until')
            ->where('reserved_until', '<', now())
            ->with('items')
            ->chunkById(50, function ($orders) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order) {
                        $locked = Order::whereKey($order->id)->lockForUpdate()->first();

                        // Re-check under lock: a callback may have paid it moments ago.
                        if ($locked->status !== OrderStatus::PendingPayment || $locked->reserved_until === null) {
                            return;
                        }

                        foreach ($order->items as $item) {
                            if ($item->variant_id) {
                                Variant::whereKey($item->variant_id)->increment('stock_quantity', $item->quantity);
                            }
                        }

                        $locked->update([
                            'status' => OrderStatus::Cancelled,
                            'reserved_until' => null,
                        ]);
                    });
                }
            });
    }
}
