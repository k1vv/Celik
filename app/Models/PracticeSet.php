<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSet extends Model
{
    protected $fillable = ['classroom_id', 'skill_id', 'title', 'description', 'difficulty', 'estimated_minutes', 'due_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /** The quiz questions bundled into this set, in authored order. */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'practice_set_questions')
            ->withPivot('order')
            ->orderBy('practice_set_questions.order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
