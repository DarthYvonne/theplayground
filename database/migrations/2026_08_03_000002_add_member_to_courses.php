<?php

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personlig træning is one trainer and one named member, so the member is a
 * to-one column rather than a pivot — a pivot would permit 0..n and then need a
 * unique index to re-impose the 1:1 it was chosen to express.
 *
 * The invite columns hold an email or phone for someone who has no account yet;
 * they are match keys only, and stop being consulted the moment member_id is set.
 */
return new class extends Migration {
    public function up(): void
    {
        // Deliberately NOT a foreign key. This database is SQLite, where adding
        // one to an existing table rebuilds it — and rebuilding `courses` fires
        // the ON DELETE CASCADE on course_schedules.course_id, silently deleting
        // every training time in the process. A plain indexed column plus the
        // guard in UserAdminController::destroy() is the safe way to say this.
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->after('type');
            $table->string('member_invite_email')->nullable()->after('member_id');
            $table->string('member_invite_phone', 40)->nullable()->after('member_invite_email');
            $table->timestamp('member_claimed_at')->nullable()->after('member_invite_phone');

            $table->index(['type', 'member_id']);
            $table->index(['type', 'member_invite_email']);
        });

        // Personlig træning used to be enrollable by anyone. Any existing one has
        // its member recorded as an enrollment — promote that to the real link,
        // or the member loses the chat the moment access starts asking for it.
        $rows = DB::table('enrollments')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->where('courses.type', Course::TYPE_PERSONLIG)
            ->whereIn('enrollments.status', ['active', 'past_due'])
            ->select('enrollments.course_id', 'enrollments.user_id')
            ->get()
            ->groupBy('course_id');

        foreach ($rows as $courseId => $enrollments) {
            // Only when it is unambiguous; anything stranger is left for a human.
            if ($enrollments->count() === 1) {
                DB::table('courses')->where('id', $courseId)->update([
                    'member_id' => $enrollments->first()->user_id,
                    'member_claimed_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['type', 'member_invite_email']);
            $table->dropIndex(['type', 'member_id']);
            $table->dropColumn(['member_id', 'member_invite_email', 'member_invite_phone', 'member_claimed_at']);
        });
    }
};
