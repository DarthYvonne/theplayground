<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('email_broadcasts');
    }

    public function down(): void
    {
        // No restore — this table is being removed as part of the
        // Beskeder migration that supersedes the email broadcast feature.
    }
};
