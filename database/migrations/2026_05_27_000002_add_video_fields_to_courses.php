<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('image_path');
            $table->string('original_video_path')->nullable()->after('video_path');
            $table->string('video_processing_status')->nullable()->after('original_video_path');
            $table->string('video_thumbnail_path')->nullable()->after('video_processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'original_video_path', 'video_processing_status', 'video_thumbnail_path']);
        });
    }
};
