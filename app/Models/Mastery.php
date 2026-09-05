<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mastery extends Model
{
    protected $fillable = [
        'user_id', 'skill_id', 'mastery_score',
        'attempts_count', 'correct_count', 'last_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'mastery_score' => 'float',
            'last_attempt_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /**
     * Whether this skill counts as a class-wide "gap" worth flagging to the teacher.
     */
    public function isStruggling(): bool
    {
        return $this->mastery_score < 0.5;
    }
}
