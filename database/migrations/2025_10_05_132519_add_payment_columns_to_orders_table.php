<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('snap_token')->nullable()->after('status');
            $table->string('payment_status')->default('unpaid')->after('snap_token'); // unpaid, paid, failed, expired
            $table->string('payment_type')->nullable()->after('payment_status');
            $table->string('transaction_id')->nullable()->after('payment_type');
            $table->decimal('total_amount', 15, 2)->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['snap_token', 'payment_status', 'payment_type', 'transaction_id', 'total_amount']);
        });
    }
};
