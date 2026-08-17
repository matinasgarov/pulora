<?php // lang/az/validation.php

/*
 * Azerbaijani validation messages.
 *
 * Only the rules this shop actually validates against are translated here; any
 * key missing from this file falls back to the framework's English through
 * config('app.fallback_locale'). That is deliberate — a half-translated copy of
 * the framework's full rule list would be a hundred lines nobody reads, and the
 * fallback means an untranslated rule still produces a sentence rather than a
 * raw key.
 *
 * `attributes` matters as much as the messages: without it the checkout says
 * "address_line1 sahəsi tələb olunur", naming a database column at the customer.
 */

return [
    'required' => ':attribute sahəsi tələb olunur.',
    'email' => ':attribute düzgün e-poçt ünvanı olmalıdır.',
    'string' => ':attribute mətn olmalıdır.',
    'integer' => ':attribute tam ədəd olmalıdır.',
    'numeric' => ':attribute rəqəm olmalıdır.',
    'boolean' => ':attribute doğru və ya yanlış olmalıdır.',
    'confirmed' => ':attribute təsdiqi uyğun gəlmir.',
    'in' => 'Seçilmiş :attribute düzgün deyil.',
    'exists' => 'Seçilmiş :attribute düzgün deyil.',
    'unique' => 'Bu :attribute artıq istifadə olunub.',
    'regex' => ':attribute formatı düzgün deyil.',

    'size' => [
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size simvol olmalıdır.',
        'array' => ':attribute :size elementdən ibarət olmalıdır.',
    ],

    'max' => [
        'numeric' => ':attribute :max-dan böyük olmamalıdır.',
        'string' => ':attribute :max simvoldan uzun olmamalıdır.',
        'array' => ':attribute :max elementdən çox olmamalıdır.',
    ],

    'min' => [
        'numeric' => ':attribute ən azı :min olmalıdır.',
        'string' => ':attribute ən azı :min simvol olmalıdır.',
        'array' => ':attribute ən azı :min elementdən ibarət olmalıdır.',
    ],

    'between' => [
        'numeric' => ':attribute :min ilə :max arasında olmalıdır.',
        'string' => ':attribute :min ilə :max simvol arasında olmalıdır.',
        'array' => ':attribute :min ilə :max element arasında olmalıdır.',
    ],

    'attributes' => [
        'email' => 'E-poçt',
        'name' => 'Ad və soyad',
        'address_line1' => 'Ünvan',
        'address_line2' => 'Ünvan (davamı)',
        'city' => 'Şəhər',
        'postcode' => 'Poçt indeksi',
        'country_code' => 'Ölkə',
        'phone' => 'Telefon',
        'shipping_rate_id' => 'Çatdırılma üsulu',
        'discount_code' => 'Endirim kodu',
        'order_number' => 'Sifariş nömrəsi',
        'quantity' => 'Say',
        'password' => 'Şifrə',
    ],
];
