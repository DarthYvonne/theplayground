<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // 'card' = recurring subscription via card; 'mobilepay' = one-time monthly via MobilePay.
            // Null for legacy rows.
            $table->string('payment_method', 32)->nullable()->after('cancel_at_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
