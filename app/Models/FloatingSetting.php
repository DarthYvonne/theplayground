<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloatingSetting extends Model
{
    protected $fillable = [
        'slot_duration_minutes','open_from','open_to','days_open',
        'price_cents','cancel_cutoff_hours','stripe_product_id','stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'slot_duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'cancel_cutoff_hours' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'slot_duration_minutes' => 60,
            'open_from' => '08:00:00',
            'open_to' => '22:00:00',
            'days_open' => 'mon,tue,wed,thu,fri,sat,sun',
            'price_cents' => 0,
            'cancel_cutoff_hours' => 24,
        ]);
    }

    /** @return array<string> */
    public function daysList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->days_open)));
    }

    public function isOpenOn(\Carbon\Carbon $date): bool
    {
        $code = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'][$date->format('D')] ?? '';
        return in_array($code, $this->daysList(), true);
    }

    public function priceLabel(): string
    {
        $amt = number_format($this->price_cents / 100, $this->price_cents % 100 === 0 ? 0 : 2, ',', '.');
        return $amt . ' kr';
    }
}
