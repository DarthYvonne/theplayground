<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floating_bookings', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('amount_cents');
            $table->string('mobilepay_payment_id')->nullable()->after('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('floating_bookings', function (Blueprint $table) {
            $table->dropColumn(['provider', 'mobilepay_payment_id']);
        });
    }
};
