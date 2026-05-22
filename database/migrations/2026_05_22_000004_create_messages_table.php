<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel_type'); // 'platform' | 'course'
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['channel_type','course_id','created_at']);
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->unique(['user_id','course_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('messages');
    }
};
