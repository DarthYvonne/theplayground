<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('floating_settings', function (Blueprint $table) {
            $table->unsignedInteger('price_cents_single')->default(0)->after('price_cents');
            $table->unsignedInteger('price_cents_double')->default(0)->after('price_cents_single');
            $table->string('stripe_price_id_single')->nullable()->after('stripe_price_id');
            $table->string('stripe_price_id_double')->nullable()->after('stripe_price_id_single');
        });

        // Carry the existing single price/stripe_price_id into the new "single" slot.
        DB::table('floating_settings')->update([
            'price_cents_single' => DB::raw('price_cents'),
            'stripe_price_id_single' => DB::raw('stripe_price_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('floating_settings', function (Blueprint $table) {
            $table->dropColumn(['price_cents_single', 'price_cents_double', 'stripe_price_id_single', 'stripe_price_id_double']);
        });
    }
};
