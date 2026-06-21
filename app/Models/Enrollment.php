<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $fillable = ['user_id', 'course_id', 'status', 'provider', 'stripe_subscription_id', 'mobilepay_agreement_id', 'enrolled_at', 'canceled_at', 'current_period_end', 'cancel_at_period_end', 'payment_method'];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'canceled_at' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** A MobilePay recurring membership the scheduler should charge each period. */
    public function isRecurringMembership(): bool
    {
        return $this->provider === 'mobilepay' && ! empty($this->mobilepay_agreement_id);
    }
}
