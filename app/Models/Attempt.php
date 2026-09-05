<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'question_id', 'practice_set_id', 'submission_id', 'skill_id', 'source', 'is_correct',
        'score', 'max_score', 'teacher_score', 'raw_answer', 'photo_path', 'confidence', 'needs_review', 'sampled_for_audit',
        'difficulty_at_attempt', 'mastery_before', 'mastery_after',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'needs_review' => 'boolean',
            'sampled_for_audit' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** The teacher-authored PracticeSet this attempt was answered from, if any. */
    public function practiceSet(): BelongsTo
    {
        return $this->belongsTo(PracticeSet::class);
    }

    /** The multi-question PDF Submission this attempt was graded from, if any. */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** AI's proposed score unless a teacher has overridden it. */
    public function effectiveScore(): ?int
    {
        return $this->teacher_score ?? $this->score;
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(AttemptDiagnosis::class);
    }
}
