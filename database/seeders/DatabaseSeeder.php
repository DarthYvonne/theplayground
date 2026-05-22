<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => env('OWNER_EMAIL', 'magnus@theplayground.local')],
            [
                'name' => env('OWNER_NAME', 'Magnus'),
                'password' => Hash::make(env('OWNER_PASSWORD', 'Elefantraket123!')),
                'role' => 'owner',
                'email_verified_at' => now(),
                'about' => 'Owner & head trainer at The Playground.',
            ]
        );

        // Demo courses only if none exist yet (idempotent).
        if (Course::count() === 0) {
            Course::create([
                'title' => 'Morning Yoga Flow',
                'description' => "Start your day with 60 minutes of energising vinyasa flow. Suitable for all levels — modifications offered every class.\n\nMondays & Wednesdays 07:00–08:00.",
                'trainer_id' => $owner->id,
                'price_cents' => 49900,
                'max_participants' => 12,
                'is_active' => true,
            ]);
            Course::create([
                'title' => 'Strength Foundations',
                'description' => "Compound lifts, mobility, and recovery. Eight-week progression with personalised loading. You'll learn how to lift safely and what to do between sessions.",
                'trainer_id' => $owner->id,
                'price_cents' => 79900,
                'max_participants' => 8,
                'is_active' => true,
            ]);
            Course::create([
                'title' => 'HIIT Express',
                'description' => "30-minute high-intensity intervals. No equipment needed. Bring water, a towel, and a willingness to sweat.",
                'trainer_id' => $owner->id,
                'price_cents' => 39900,
                'max_participants' => 20,
                'is_active' => true,
            ]);
        }
    }
}
