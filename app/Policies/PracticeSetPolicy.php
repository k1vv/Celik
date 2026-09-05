<?php

namespace App\Policies;

use App\Models\PracticeSet;
use App\Models\User;

class PracticeSetPolicy
{
    /**
     * Whether the student may answer a question from this set - only if
     * the teacher released it to one of the student's own classrooms.
     * Narrower than QuestionPolicy::attemptQuiz (subject-level), since a set
     * is scoped to one specific classroom, not every classroom of a subject.
     */
    public function attempt(User $student, PracticeSet $practiceSet): bool
    {
        return $student->classrooms()->where('classrooms.id', $practiceSet->classroom_id)->exists();
    }
}
