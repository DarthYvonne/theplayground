<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';
    protected $fillable = ['user_id','type','title','body','link','course_id','actor_id','read_at'];
    protected function casts(): array { return ['read_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
}
