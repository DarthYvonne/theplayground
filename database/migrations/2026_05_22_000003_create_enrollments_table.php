<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active | canceled | past_due | pending
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['course_id','status']);
            $table->unique(['user_id','course_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('enrollments'); }
};
