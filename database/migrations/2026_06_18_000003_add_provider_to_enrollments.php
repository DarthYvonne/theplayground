<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Which rail this enrollment is billed on. mobilepay = recurring agreement;
            // stripe = one-time card payment granting one month at a time.
            $table->string('provider', 32)->nullable()->after('status');
            // MobilePay recurring agreement reference (null for one-time/stripe).
            $table->string('mobilepay_agreement_id')->nullable()->after('stripe_subscription_id');
            $table->index('mobilepay_agreement_id');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['mobilepay_agreement_id']);
            $table->dropColumn(['provider', 'mobilepay_agreement_id']);
        });
    }
};
