<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->timestamp('current_period_end')->nullable()->after('canceled_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('current_period_end');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['current_period_end', 'cancel_at_period_end']);
        });
    }
};
