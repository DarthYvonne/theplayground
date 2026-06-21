<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generic webhook de-duplication across providers. A duplicate insert
        // (UNIQUE provider+event_id) means we already processed the event and can
        // safely ack it. Replaces the Stripe-only `stripe_events` table.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id');
            $table->string('type')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
