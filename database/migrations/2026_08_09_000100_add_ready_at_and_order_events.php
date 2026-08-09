<?php // database/migrations/2026_08_09_000100_add_ready_at_and_order_events.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // tracking_number and shipped_at already exist from create_order_tables.
        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('ready_at')->nullable()->after('shipped_at');
        });

        Schema::create('order_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status');
            $t->string('to_status');
            $t->text('note')->nullable();
            // Not a foreign key: the audit trail must survive independently of
            // the users table (deleted operators, synthetic ids in tests), and
            // TransitionTest exercises userId with no corresponding user row.
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');

        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn('ready_at');
        });
    }
};
