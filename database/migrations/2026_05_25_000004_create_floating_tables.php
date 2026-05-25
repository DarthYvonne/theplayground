<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('floating_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active','sort_order']);
        });

        Schema::create('floating_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('slot_duration_minutes')->default(60);
            $table->time('open_from')->default('08:00:00');
            $table->time('open_to')->default('22:00:00');
            $table->string('days_open', 80)->default('mon,tue,wed,thu,fri,sat,sun'); // csv of weekday codes
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedSmallInteger('cancel_cutoff_hours')->default(24);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });

        // Single-row defaults.
        DB::table('floating_settings')->insert([
            'slot_duration_minutes' => 60,
            'open_from' => '08:00:00',
            'open_to' => '22:00:00',
            'days_open' => 'mon,tue,wed,thu,fri,sat,sun',
            'price_cents' => 0,
            'cancel_cutoff_hours' => 24,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('floating_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('floating_devices')->cascadeOnDelete();
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            // pending: created, awaiting Stripe return | active: paid (or free-confirmed) | cancelled
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['device_id','slot_start'], 'floating_bookings_device_slot_unique');
            $table->index(['user_id','slot_start']);
            $table->index(['status','slot_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floating_bookings');
        Schema::dropIfExists('floating_settings');
        Schema::dropIfExists('floating_devices');
    }
};
