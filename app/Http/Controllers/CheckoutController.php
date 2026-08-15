<?php // app/Http/Controllers/CheckoutController.php

namespace App\Http\Controllers;

use App\Domain\Checkout\PlaceOrder;
use App\Domain\Order\CustomerDetails;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $customer = new CustomerDetails(
            email: $request->validated('email'),
            name: $request->validated('name'),
            addressLine1: $request->validated('address_line1'),
            addressLine2: $request->validated('address_line2'),
            city: $request->validated('city'),
            postcode: $request->validated('postcode'),
            countryCode: $request->validated('country_code'),
            phone: $request->validated('phone'),
        );

        $result = $placeOrder(
            $customer,
            (int) $request->validated('shipping_rate_id'),
            $request->validated('discount_code'),
            app()->getLocale(),
        );

        if (! $result->succeeded) {
            return back()->withErrors([$result->errorField => $result->errorMessage])->withInput();
        }

        return redirect()->away($result->redirectUrl);
    }
}
