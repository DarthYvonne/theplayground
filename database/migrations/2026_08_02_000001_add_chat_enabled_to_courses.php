<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fællestræning has no enrollment, so its chat audience is every paying
 * member — gym-wide. That is useful for some sessions and noise for others,
 * so it is chosen per training. Hold ignore the flag and always have chat.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('chat_enabled')->default(true)->after('free_enrollment');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('chat_enabled');
        });
    }
};
