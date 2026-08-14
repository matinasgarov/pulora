<?php // app/Filament/Actions/TransitionActions.php

namespace App\Filament\Actions;

use App\Domain\Order\IllegalTransitionException;
use App\Domain\Order\Models\Order;
use App\Domain\Order\OrderService;
use App\Domain\Order\OrderStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Every admin status change in the panel is built here, so there is exactly one
 * place that calls OrderService::transition(). Nothing in Filament writes
 * orders.status.
 */
class TransitionActions
{
    public static function startProduction(): Action
    {
        return Action::make('start_production')
            ->label('Start making')
            ->icon('heroicon-o-play')
            ->visible(fn (?Order $record) => $record?->status->canTransitionTo(OrderStatus::InProduction) ?? false)
            ->action(fn (Order $record) => static::run($record, OrderStatus::InProduction));
    }

    public static function markReady(): Action
    {
        return Action::make('mark_ready')
            ->label('Mark made')
            ->icon('heroicon-o-check')
            ->visible(fn (?Order $record) => $record && $record->status === OrderStatus::InProduction && $record->ready_at === null)
            ->action(function (Order $record) {
                try {
                    app(OrderService::class)->markReady($record);
                } catch (IllegalTransitionException|RuntimeException $e) {
                    // Same shared handling as run(): a stale Workshop page (second
                    // tab, or the order changed status between render and click)
                    // must show the operator a red notification, not an
                    // uncaught-exception error page.
                    Notification::make()->danger()->title('Could not mark ready')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Marked as made.')->send();
            });
    }

    public static function ship(): Action
    {
        return Action::make('ship')
            ->label('Mark posted')
            ->icon('heroicon-o-truck')
            ->visible(fn (?Order $record) => $record?->status->canTransitionTo(OrderStatus::Shipped) ?? false)
            ->schema([
                TextInput::make('tracking_number')->required()->label('Tracking number'),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                $record,
                OrderStatus::Shipped,
                trackingNumber: $data['tracking_number'],
            ));
    }

    public static function deliver(): Action
    {
        return Action::make('deliver')
            ->label('Mark delivered')
            ->visible(fn (?Order $record) => $record?->status->canTransitionTo(OrderStatus::Delivered) ?? false)
            ->action(fn (Order $record) => static::run($record, OrderStatus::Delivered));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Cancelling returns this order\'s capacity to the variants.')
            ->visible(fn (?Order $record) => $record?->status->canTransitionTo(OrderStatus::Cancelled) ?? false)
            ->schema([Textarea::make('note')->label('Why?')->required()])
            ->action(fn (Order $record, array $data) => static::run($record, OrderStatus::Cancelled, $data['note']));
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (?Order $record) => $record?->status->canTransitionTo(OrderStatus::Refunded) ?? false)
            ->schema([
                Textarea::make('note')->label('Why?')->required(),
                Toggle::make('restore_capacity')
                    ->label('Return capacity to the variant')
                    ->helperText('Leave off if the piece was already made.')
                    ->default(false),
            ])
            ->action(fn (Order $record, array $data) => static::run(
                $record,
                OrderStatus::Refunded,
                $data['note'],
                restoreCapacity: $data['restore_capacity'],
            ));
    }

    private static function run(
        Order $order,
        OrderStatus $to,
        ?string $note = null,
        bool $restoreCapacity = false,
        ?string $trackingNumber = null,
    ): void {
        try {
            app(OrderService::class)->transition(
                order: $order,
                to: $to,
                note: $note,
                userId: auth()->id(),
                restoreCapacity: $restoreCapacity,
                trackingNumber: $trackingNumber,
            );
        } catch (IllegalTransitionException|RuntimeException $e) {
            // The gateway refusing a refund is the case that matters here: the
            // operator must see what actually happened, never an assumed success.
            Notification::make()->danger()->title('Could not update the order')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title("Order is now {$to->label()}.")->send();
    }
}
