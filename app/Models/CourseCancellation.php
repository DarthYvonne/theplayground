<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCancellation extends Model
{
    protected $fillable = ['course_id', 'occurrence_date', 'reason', 'cancelled_by'];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'date',
        ];
    }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    /**
     * Build a lookup map of cancellations for the given courses within a date
     * range. Keys are "<course_id>:<YYYY-MM-DD>" for cheap O(1) per-event lookup
     * during rendering.
     *
     * @param  array<int> $courseIds
     * @return array<string, self>
     */
    public static function mapForRange(array $courseIds, Carbon $start, Carbon $end): array
    {
        if (empty($courseIds)) return [];
        return self::whereIn('course_id', $courseIds)
            ->whereBetween('occurrence_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($c) => $c->course_id . ':' . $c->occurrence_date->toDateString())
            ->all();
    }
}
