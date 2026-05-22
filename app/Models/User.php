<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'phone', 'about', 'picture_path',
        'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'last_seen_platform_chat_at' => 'datetime',
        ];
    }

    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function activeEnrollments(): HasMany { return $this->enrollments()->where('status', 'active'); }
    public function trainerCourses(): HasMany { return $this->hasMany(Course::class, 'trainer_id'); }
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function notifications(): HasMany { return $this->hasMany(AppNotification::class)->latest(); }

    public function isOwner(): bool { return $this->role === 'owner'; }
    public function isTrainer(): bool { return $this->role === 'trainer' || $this->role === 'owner'; }
    public function isUser(): bool { return $this->role === 'user'; }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        return strtoupper(substr($parts[0] ?? '?', 0, 1) . substr($parts[1] ?? '', 0, 1)) ?: '?';
    }

    public function pictureUrl(): ?string
    {
        return $this->picture_path ? Storage::disk('public')->url($this->picture_path) : null;
    }

    public function enrolledIn(Course $course): bool
    {
        return $this->enrollments()->where('course_id', $course->id)->where('status', 'active')->exists();
    }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }

    public function unreadMessageCount(): int
    {
        $courses = $this->activeEnrollments()->pluck('course_id')->all();
        if ($this->isTrainer()) {
            $courses = array_unique(array_merge($courses, $this->trainerCourses()->pluck('id')->all()));
        }
        if (empty($courses)) return 0;
        $reads = MessageRead::where('user_id', $this->id)->whereIn('course_id', $courses)->pluck('last_read_at', 'course_id');
        $total = 0;
        foreach ($courses as $cid) {
            $cutoff = $reads[$cid] ?? null;
            $q = Message::where('channel_type', 'course')->where('course_id', $cid)->where('user_id', '!=', $this->id);
            if ($cutoff) $q->where('created_at', '>', $cutoff);
            $total += $q->count();
        }
        return $total;
    }
}
