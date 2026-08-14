<?php // database/migrations/2026_08_14_000200_add_locale_to_orders.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // The language the customer actually bought in. Drives which
            // language confirmation and shipment emails go out in.
            $t->string('locale', 5)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('locale');
        });
    }
};
