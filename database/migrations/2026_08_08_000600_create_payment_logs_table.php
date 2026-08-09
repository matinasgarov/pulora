<?php // database/migrations/2026_08_08_000600_create_payment_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('gateway');
            $t->string('direction');            // request | callback
            $t->string('reference')->nullable()->index();
            $t->json('payload');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
