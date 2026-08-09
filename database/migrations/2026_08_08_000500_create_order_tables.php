<?php // database/migrations/2026_08_08_000500_create_order_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number')->unique();
            $t->string('status')->default('pending_payment');
            $t->string('source')->default('web');            // web | shopify | manual

            $t->string('customer_email');
            $t->string('customer_name');
            $t->string('phone')->nullable();

            $t->string('address_line1');
            $t->string('address_line2')->nullable();
            $t->string('city');
            $t->string('postcode')->nullable();
            $t->string('country_code', 2);

            $t->unsignedInteger('subtotal_minor');
            $t->unsignedInteger('shipping_minor');
            $t->unsignedInteger('discount_minor')->default(0);
            $t->unsignedInteger('total_minor');
            $t->string('currency', 3)->default('AZN');

            $t->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('shipping_rate_id')->nullable()->constrained()->nullOnDelete();

            $t->string('payment_reference')->nullable()->index();
            $t->string('tracking_number')->nullable();

            $t->string('customs_contents')->nullable();
            $t->unsignedInteger('customs_value_minor')->nullable();

            $t->unsignedInteger('total_weight_grams')->default(0);

            $t->timestamp('reserved_until')->nullable()->index();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot columns — never joined live.
            $t->string('product_name');
            $t->string('variant_description');
            $t->string('sku');
            $t->unsignedInteger('unit_price_minor');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('line_total_minor');
            $t->json('personalization')->nullable();
            $t->unsignedInteger('weight_grams')->default(0);

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
