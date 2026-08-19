<?php // config/shop.php

return [

    /*
     * Where payment anomaly mail goes.
     *
     * `?:` rather than env()'s own default: an unset variable falls back, but
     * `SHOP_OPERATOR_EMAIL=` with nothing after it returns an empty string,
     * which does not. That is what was in .env, so anomaly mail was being
     * addressed to nobody — and every test that touches it sets the value
     * itself, so nothing noticed.
     */
    'operator_email' => env('SHOP_OPERATOR_EMAIL') ?: 'owner@example.com',

    /*
     * Whether customers can put things in a bag and order them.
     *
     * Off for the public preview, which exists so people can see the pieces
     * before the shop opens. With it off the bag and checkout are unreachable
     * — not merely hidden — so nothing can reach the payment gateway, and the
     * storefront cannot take an order it has no way to fulfil.
     *
     * This is also what keeps the preview off the mock payment driver, which
     * refuses to run outside local/testing precisely so a fake gateway can
     * never accept a real order.
     */
    'ordering' => env('PULORA_ORDERING_ENABLED', true),

];
