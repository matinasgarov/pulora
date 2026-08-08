<?php // database/migrations/2026_08_08_000300_create_shipping_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $t) {
            $t->id();
            $t->string('name');                       // "Azerbaijan", "Regional", "Rest of world"
            $t->json('country_codes');                // ["AZ"] — empty array means catch-all
            $t->boolean('is_fallback')->default(false);
            $t->timestamps();
        });

        Schema::create('shipping_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $t->string('name');                       // "Standard", "Express"
            $t->unsignedInteger('min_weight_grams')->default(0);
            $t->unsignedInteger('max_weight_grams');
            $t->unsignedInteger('price_minor');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_zones');
    }
};
