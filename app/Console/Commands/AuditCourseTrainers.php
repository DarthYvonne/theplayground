<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Finds course trainer assignments that predate the guard in
 * UserAdminController::updateRole() — users who were demoted while still
 * assigned to a hold. They keep a "Træner" badge on the members page and a
 * "Send besked til alle" button that fails, because BeskederController's
 * canBroadcastTo() also requires the trainer/owner role.
 *
 * Report-only by design: detaching blindly can leave a course with no trainer
 * at all, which hides it from the trainer calendar and locks trainers out of
 * course media. Fix the ones it lists via Indstillinger → Brugere.
 */
class AuditCourseTrainers extends Command
{
    protected $signature = 'trainers:audit';

    protected $description = 'List course trainer assignments held by users who are no longer trainers or owners';

    public function handle(): int
    {
        $stale = User::whereNotIn('role', ['owner', 'trainer'])
            ->whereHas('trainerCourses')
            ->with('trainerCourses')
            ->orderBy('name')
            ->get();

        if ($stale->isEmpty()) {
            $this->info('Ingen problemer: alle trænere på hold har rollen træner eller ejer.');
            return self::SUCCESS;
        }

        $this->warn($stale->count() . ' bruger(e) står som træner uden at have rollen:');
        $this->newLine();

        foreach ($stale as $user) {
            $this->line("  {$user->name} ({$user->email}) — rolle: {$user->role}");
            foreach ($user->trainerCourses as $course) {
                $others = $course->trainers->where('id', '!=', $user->id)
                    ->whereIn('role', ['owner', 'trainer'])
                    ->count();
                $note = $others === 0 ? ' [eneste træner — vælg en afløser først]' : '';
                $this->line("    - {$course->title}{$note}");
            }
        }

        $this->newLine();
        $this->line('Ret dem under Indstillinger → Brugere → vælg brugeren → "Underviser på" → Skift træner.');

        return self::SUCCESS;
    }
}
