<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    /**
     * Whether the student may submit a homework answer for this question -
     * only if a teacher has actually released it (via an Assignment) to one
     * of the student's own classrooms. Without this, a student who knows or
     * guesses a question_id could submit homework never assigned to them.
     */
    public function submitHomework(User $student, Question $question): bool
    {
        return $question->assignments()
            ->whereIn('classroom_id', $student->classrooms()->pluck('classrooms.id'))
            ->exists();
    }

    /**
     * Whether the student may submit a quiz answer for this question -
     * only if it belongs to the same subject as one of their own classrooms.
     * Question is a shared subject-level bank (not classroom-owned, see
     * architecture.md), so this checks subject membership rather than a
     * specific Assignment the way submitHomework() does.
     */
    public function attemptQuiz(User $student, Question $question): bool
    {
        return $student->classrooms()
            ->where('subject_id', $question->skill->subject_id)
            ->exists();
    }
}
