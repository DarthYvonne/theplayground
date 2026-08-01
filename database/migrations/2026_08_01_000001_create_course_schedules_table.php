<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A course used to carry one weekday list and one start/end time, so every day
 * it ran had to share the same hours. Each slot is now its own row, which also
 * allows two slots on the same weekday.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('weekday', 3);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->index(['course_id', 'weekday']);
        });

        foreach (DB::table('courses')->get() as $course) {
            if (empty($course->weekdays)) continue;
            $rows = [];
            foreach (array_filter(explode(',', $course->weekdays)) as $day) {
                $rows[] = [
                    'course_id' => $course->id,
                    'weekday' => $day,
                    'start_time' => $course->start_time,
                    'end_time' => $course->end_time,
                ];
            }
            if ($rows) DB::table('course_schedules')->insert($rows);
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['weekdays', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('weekdays')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
        });

        // Collapses back to one time pair per course — the earliest slot wins.
        foreach (DB::table('courses')->pluck('id') as $courseId) {
            $slots = DB::table('course_schedules')->where('course_id', $courseId)->orderBy('start_time')->get();
            if ($slots->isEmpty()) continue;
            DB::table('courses')->where('id', $courseId)->update([
                'weekdays' => $slots->pluck('weekday')->unique()->implode(','),
                'start_time' => $slots->first()->start_time,
                'end_time' => $slots->first()->end_time,
            ]);
        }

        Schema::dropIfExists('course_schedules');
    }
};
