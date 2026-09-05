<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'skill_id', 'type', 'body', 'options', 'correct_option', 'difficulty',
        'distractor_map', 'kssm_code', 'expected_answer',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'distractor_map' => 'array',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /** The Assignment PDFs this question has been bundled into, if any - mirrors practiceSets(). */
    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'assignment_questions')
            ->withPivot('order');
    }
}
