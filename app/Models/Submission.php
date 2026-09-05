<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's PDF upload for one Assignment - the grouping around the
 * several per-question Attempts a multi-question homework submission
 * produces (see attempts.submission_id).
 */
class Submission extends Model
{
    protected $fillable = ['user_id', 'assignment_id', 'photo_path', 'extraction_status', 'teacher_validated_at'];

    protected function casts(): array
    {
        return [
            'teacher_validated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
