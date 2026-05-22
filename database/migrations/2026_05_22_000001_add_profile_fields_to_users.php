<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password');
            $table->string('phone')->nullable()->after('role');
            $table->text('about')->nullable()->after('phone');
            $table->string('picture_path')->nullable()->after('about');
            $table->timestamp('last_seen_platform_chat_at')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role','phone','about','picture_path','last_seen_platform_chat_at','stripe_id','pm_type','pm_last_four','trial_ends_at']);
        });
    }
};
