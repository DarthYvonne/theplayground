<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // video | audio | image
            $table->string('title');
            $table->text('description')->nullable();

            // Audio & image files live here. For videos this stays null and the
            // processed file lands in video_path (set by ProcessVideoJob).
            $table->string('file_path')->nullable();

            // Video pipeline columns — shared with the ProcessVideoJob contract.
            $table->string('video_path')->nullable();
            $table->string('original_video_path')->nullable();
            $table->string('video_thumbnail_path')->nullable();
            $table->string('video_processing_status')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
