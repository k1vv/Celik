<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = ['classroom_id', 'title', 'due_at'];

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

    /** The questions bundled into this assignment's PDF, in authored order - mirrors PracticeSet::questions(). */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'assignment_questions')
            ->withPivot('order')
            ->orderBy('assignment_questions.order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
