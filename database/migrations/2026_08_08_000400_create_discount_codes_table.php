<?php // database/migrations/2026_08_08_000400_create_discount_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('kind');                                  // percent | fixed
            $t->unsignedInteger('value');                        // percent points, or minor units
            $t->unsignedInteger('minimum_order_minor')->default(0);
            $t->unsignedInteger('usage_limit')->nullable();      // null = unlimited
            $t->unsignedInteger('times_used')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
