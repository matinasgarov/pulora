<?php // app/Livewire/CheckoutForm.php

namespace App\Livewire;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\PlaceOrder;
use App\Domain\Order\CustomerDetails;
use App\Domain\Shipping\ShippingCalculator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class CheckoutForm extends Component
{
    public string $email = '';
    public string $name = '';
    public string $phone = '';
    public string $address_line1 = '';
    public string $address_line2 = '';
    public string $city = '';
    public string $postcode = '';
    public string $country_code = 'AZ';
    public ?int $shipping_rate_id = null;
    public string $discount_code = '';

    /**
     * Shipping quotes for the current country and cart weight, as plain
     * arrays — Livewire cannot synthesize the readonly ShippingQuote value
     * object as a public property, so refreshQuotes() flattens it here.
     * @var array{rateId: int, name: string, priceMinor: int}[]
     */
    public array $quotes = [];

    public function mount(): void
    {
        $this->refreshQuotes();
    }

    /** Livewire calls this automatically whenever country_code changes. */
    public function updatedCountryCode(): void
    {
        $this->shipping_rate_id = null;
        $this->refreshQuotes();
    }

    /**
     * Shipping is priced by the domain, never in the view: the calculator owns
     * the zone and weight-bracket rules.
     */
    private function refreshQuotes(): void
    {
        $weight = app(CartService::class)->snapshot()->totalWeightGrams();

        $quotes = app(ShippingCalculator::class)
            ->quotesFor($this->country_code, $weight);

        $this->quotes = array_map(fn ($q) => [
            'rateId' => $q->rateId,
            'name' => $q->name,
            'priceMinor' => $q->priceMinor,
        ], $quotes);

        if (count($this->quotes) === 1) {
            $this->shipping_rate_id = $this->quotes[0]['rateId'];
        }
    }

    public function submit(PlaceOrder $placeOrder)
    {
        // Mirror CheckoutRequest's rules. Read that class and reconcile any
        // difference in its favour — it guards the POST route that Plan 1's
        // tests cover.
        $this->validate([
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'shipping_rate_id' => ['required', 'integer'],
        ]);

        $result = $placeOrder(
            new CustomerDetails(
                email: $this->email,
                name: $this->name,
                addressLine1: $this->address_line1,
                addressLine2: $this->address_line2 ?: null,
                city: $this->city,
                postcode: $this->postcode ?: null,
                countryCode: $this->country_code,
                phone: $this->phone ?: null,
            ),
            $this->shipping_rate_id,
            $this->discount_code ?: null,
            app()->getLocale(),
        );

        if (! $result->succeeded) {
            $this->addError($result->errorField ?? 'email', $result->errorMessage);

            return null;
        }

        return redirect($result->redirectUrl);
    }

    public function render()
    {
        $snapshot = app(CartService::class)->snapshot();

        $selectedShippingMinor = collect($this->quotes)
            ->firstWhere('rateId', $this->shipping_rate_id)['priceMinor'] ?? 0;

        return view('livewire.checkout-form', [
            'snapshot' => $snapshot,
            'totalMinor' => $snapshot->subtotalMinor() + $selectedShippingMinor,
        ]);
    }
}
