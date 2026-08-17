<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The `test@example.com` account this shipped with was Laravel's scaffolding,
     * and UserFactory gives it the password "password". It could not reach the
     * admin panel — canAccessPanel() requires is_operator, which defaults to
     * false — but a known-credential account has no business being created by a
     * production seed run. Operators are made deliberately:
     *
     *   php artisan tinker
     *   User::create([...])->update(['is_operator' => true]);
     *
     * The storefront's own seeders (shipping, discounts, product images) are
     * invoked by name rather than from here, so this stays empty on purpose.
     */
    public function run(): void
    {
        //
    }
}
