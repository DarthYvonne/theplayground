<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'email_on_message',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'trial_ends_at' => 'datetime',
            'last_seen_platform_chat_at' => 'datetime',
            'email_on_message' => 'boolean',
        ];
    }

    public function enrollments(): HasMany { return $this->hasMany(Enrollment::class); }
    public function activeEnrollments(): HasMany { return $this->enrollments()->where('status', 'active'); }
    public function trainerCourses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_trainer')->withTimestamps();
    }
    public function messages(): HasMany { return $this->hasMany(Message::class); }
    public function notifications(): HasMany { return $this->hasMany(AppNotification::class)->latest(); }

    public function effectiveRole(): string
    {
        if ($this->role !== 'owner') return $this->role;
        return session('preview_role', $this->role);
    }

    public function isActualOwner(): bool { return $this->role === 'owner'; }
    public function isOwner(): bool { return $this->effectiveRole() === 'owner'; }
    public function isTrainer(): bool { $r = $this->effectiveRole(); return $r === 'trainer' || $r === 'owner'; }
    public function isAssistant(): bool { return $this->effectiveRole() === 'assistant'; }
    public function isUser(): bool { return $this->effectiveRole() === 'user'; }

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

    public function unreadDirectMessageCount(): int
    {
        return DirectMessage::where('recipient_id', $this->id)->whereNull('read_at')->count();
    }
}
