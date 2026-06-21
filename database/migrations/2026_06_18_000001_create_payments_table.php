<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider-agnostic ledger of every payment attempt (one-time + recurring).
        // Doubles as the audit trail, the idempotency guard for the recurring
        // scheduler, and the source for the owner run-summary email.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('floating_booking_id')->nullable()->constrained('floating_bookings')->nullOnDelete();

            $table->string('provider', 32);           // mobilepay | stripe
            $table->string('kind', 16);               // one_time | recurring
            $table->string('external_id')->nullable(); // provider charge / payment reference
            $table->string('agreement_id')->nullable(); // mobilepay recurring agreement

            $table->integer('amount_cents');
            $table->string('currency', 3)->default('dkk');
            $table->string('status', 16)->default('pending'); // pending|reserved|captured|failed|refunded

            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('due_at')->nullable();

            // Recurring: "{agreement_id}:{period_start}" — guarantees the scheduler
            // never creates two charges for the same membership period.
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index('external_id');
            $table->index('agreement_id');
            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
