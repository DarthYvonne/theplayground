<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Owners can attach a Mediebibliotek item to a feed post. Deleting
            // the library item simply detaches it from old posts.
            $table->foreignId('media_item_id')->nullable()->after('video_processing_status')
                ->constrained('media_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_item_id');
        });
    }
};
