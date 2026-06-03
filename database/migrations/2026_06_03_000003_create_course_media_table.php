<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Either a reference into the Mediebibliotek…
            $table->foreignId('media_item_id')->nullable()->constrained('media_items')->nullOnDelete();

            // …or a direct upload (same shape as media_items / the video pipeline).
            $table->string('type'); // video | audio | image
            $table->string('file_path')->nullable();
            $table->string('video_path')->nullable();
            $table->string('original_video_path')->nullable();
            $table->string('video_thumbnail_path')->nullable();
            $table->string('video_processing_status')->nullable();

            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_media');
    }
};
