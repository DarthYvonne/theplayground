<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single payment attempt against a provider — one row per one-time payment or
 * per recurring charge. See the create_payments_table migration for the role of
 * each column (audit trail, scheduler idempotency, owner run-summary source).
 */
class Payment extends Model
{
    public const KIND_ONE_TIME = 'one_time';

    public const KIND_RECURRING = 'recurring';

    public const PENDING = 'pending';

    public const RESERVED = 'reserved';

    public const CAPTURED = 'captured';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    protected $fillable = [
        'user_id', 'enrollment_id', 'floating_booking_id',
        'provider', 'kind', 'external_id', 'agreement_id',
        'amount_cents', 'currency', 'status',
        'period_start', 'period_end', 'due_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function floatingBooking(): BelongsTo
    {
        return $this->belongsTo(FloatingBooking::class);
    }

    /** Idempotency key for one period of a recurring agreement. */
    public static function recurringKey(string $agreementId, \DateTimeInterface $periodStart): string
    {
        return $agreementId.':'.$periodStart->format('Y-m-d');
    }
}
