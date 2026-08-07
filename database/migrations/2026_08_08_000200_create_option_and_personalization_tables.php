<?php // database/migrations/2026_08_08_000200_create_option_and_personalization_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('variant_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');               // "Leather colour"
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('option_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_option_id')->constrained()->cascadeOnDelete();
            $t->string('value');              // "Cognac"
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('option_value_variant', function (Blueprint $t) {
            $t->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('option_value_id')->constrained()->cascadeOnDelete();
            $t->primary(['variant_id', 'option_value_id']);
        });

        Schema::create('personalization_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('type');               // monogram | gift_wrap | custom_stamp
            $t->string('label');
            $t->unsignedInteger('price_delta_minor')->default(0);
            $t->unsignedSmallInteger('max_characters')->default(3);
            $t->string('allowed_pattern')->default('/^[A-Z]+$/');
            $t->boolean('is_required')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personalization_options');
        Schema::dropIfExists('option_value_variant');
        Schema::dropIfExists('option_values');
        Schema::dropIfExists('variant_options');
    }
};
