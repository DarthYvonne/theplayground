<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('max_participants')->default(10);
            $table->boolean('is_active')->default(false);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
            $table->index(['is_active','created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('courses'); }
};
