<?php // app/Filament/Pages/Workshop.php

namespace App\Filament\Pages;

use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderStatus;
use App\Filament\Actions\TransitionActions;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class Workshop extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static ?string $title = 'Workshop';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.workshop';

    // This page replaces Filament\Pages\Dashboard as the panel home, so it
    // takes over Dashboard's root route path the same way Dashboard does.
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public Collection $toMake;
    public Collection $inProduction;
    public Collection $readyToPost;

    public int $awaitingPayment = 0;
    public int $overdue = 0;
    public int $revenueThisMonthMinor = 0;

    public function mount(): void
    {
        $this->loadQueue();
    }

    public function loadQueue(): void
    {
        // eager-load items: the cards read the snapshot, never the live catalogue
        $base = fn () => Order::with('items')->orderBy('paid_at');

        $this->toMake = $base()->where('status', OrderStatus::Paid)->get();

        $inProduction = $base()->where('status', OrderStatus::InProduction)->get();

        $this->inProduction = $inProduction->whereNull('ready_at')->values();
        $this->readyToPost = $inProduction->whereNotNull('ready_at')->values();

        $this->awaitingPayment = Order::where('status', OrderStatus::PendingPayment)->count();

        $this->overdue = Order::whereIn('status', [OrderStatus::Paid, OrderStatus::InProduction])
            ->where('paid_at', '<', now()->subDays(7))
            ->count();

        $this->revenueThisMonthMinor = (int) Order::whereNotIn('status', [
                OrderStatus::PendingPayment,
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
            ])
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total_minor');
    }

    protected function getHeaderActions(): array
    {
        // No record is bound until a card's button mounts the action with an
        // {order: id} argument, so this resolver must tolerate an empty
        // $arguments array during the page's initial render.
        $resolveRecord = fn (array $arguments) => isset($arguments['order'])
            ? Order::find($arguments['order'])
            : null;

        return [
            TransitionActions::startProduction()
                ->record($resolveRecord)
                ->after(fn (Workshop $livewire) => $livewire->loadQueue()),
            TransitionActions::markReady()
                ->record($resolveRecord)
                ->after(fn (Workshop $livewire) => $livewire->loadQueue()),
            TransitionActions::ship()
                ->record($resolveRecord)
                ->after(fn (Workshop $livewire) => $livewire->loadQueue()),
        ];
    }
}
