<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Playlists can be shared in the feed and on a Hold's Medier tab.
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('playlist_id')->nullable()->after('media_item_id')
                ->constrained('playlists')->nullOnDelete();
        });
        Schema::table('course_media', function (Blueprint $table) {
            $table->foreignId('playlist_id')->nullable()->after('media_item_id')
                ->constrained('playlists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('playlist_id');
        });
        Schema::table('course_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('playlist_id');
        });
    }
};
