<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_trainer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_id','user_id']);
            $table->index('user_id');
        });

        $now = now();
        DB::table('courses')->whereNotNull('trainer_id')->orderBy('id')->each(function ($course) use ($now) {
            DB::table('course_trainer')->insertOrIgnore([
                'course_id' => $course->id,
                'user_id' => $course->trainer_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['trainer_id']);
            $table->dropColumn('trainer_id');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('trainer_id')->nullable()->after('description')->constrained('users')->cascadeOnDelete();
        });

        DB::table('course_trainer')
            ->select('course_id', DB::raw('MIN(user_id) as user_id'))
            ->groupBy('course_id')
            ->get()
            ->each(function ($row) {
                DB::table('courses')->where('id', $row->course_id)->update(['trainer_id' => $row->user_id]);
            });

        Schema::dropIfExists('course_trainer');
    }
};
