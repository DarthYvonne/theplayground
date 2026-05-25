<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('floating_devices', function (Blueprint $table) {
            $table->string('type', 16)->default('single')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('floating_devices', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
