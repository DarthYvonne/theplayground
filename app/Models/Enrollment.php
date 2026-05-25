<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    protected $fillable = ['user_id','course_id','status','stripe_subscription_id','enrolled_at','canceled_at','current_period_end','cancel_at_period_end'];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'canceled_at' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
