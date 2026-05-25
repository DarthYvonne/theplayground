<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloatingSetting extends Model
{
    protected $fillable = [
        'slot_duration_minutes','open_from','open_to','days_open',
        'price_cents','price_cents_single','price_cents_double',
        'cancel_cutoff_hours',
        'stripe_product_id','stripe_price_id','stripe_price_id_single','stripe_price_id_double',
    ];

    protected function casts(): array
    {
        return [
            'slot_duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'price_cents_single' => 'integer',
            'price_cents_double' => 'integer',
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
            'price_cents_single' => 0,
            'price_cents_double' => 0,
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

    public function priceCentsFor(string $type): int
    {
        return $type === 'double' ? (int) $this->price_cents_double : (int) $this->price_cents_single;
    }

    public function stripePriceIdFor(string $type): ?string
    {
        return $type === 'double' ? $this->stripe_price_id_double : $this->stripe_price_id_single;
    }

    public function priceLabelFor(string $type): string
    {
        return self::formatKr($this->priceCentsFor($type));
    }

    public function priceLabel(): string
    {
        return self::formatKr((int) $this->price_cents_single);
    }

    private static function formatKr(int $cents): string
    {
        $kr = $cents / 100;
        $amt = number_format($kr, $cents % 100 === 0 ? 0 : 2, ',', '.');
        return $amt . ' kr';
    }
}
