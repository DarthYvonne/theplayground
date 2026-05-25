<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FloatingBooking extends Model
{
    protected $fillable = [
        'user_id','device_id','slot_start','slot_end','status','amount_cents',
        'stripe_session_id','stripe_payment_intent_id','paid_at','cancelled_at','cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'slot_start' => 'datetime',
            'slot_end' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function device(): BelongsTo { return $this->belongsTo(FloatingDevice::class, 'device_id'); }
    public function canceller(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function isCancellable(int $cutoffHours): bool
    {
        if ($this->isCancelled()) return false;
        return $this->slot_start->copy()->subHours($cutoffHours)->isFuture();
    }
}
