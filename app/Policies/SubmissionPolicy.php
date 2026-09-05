<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * Whether the student may submit a PDF for this assignment - only if
     * it's released to one of their own classrooms. Mirrors
     * PracticeSetPolicy::attempt (a classroom-scoped check), since
     * Assignment - like PracticeSet - belongs directly to one classroom.
     */
    public function create(User $student, Assignment $assignment): bool
    {
        return $student->classrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
    }

    /**
     * Whether the teacher may view or validate this submission - only if
     * they teach the classroom its assignment was released to. Governs both
     * SubmissionController::show() and ::validate() - viewing and
     * overriding AI scores share the same authorization boundary.
     */
    public function validate(User $teacher, Submission $submission): bool
    {
        return $teacher->classroomsTeaching()->where('classrooms.id', $submission->assignment->classroom_id)->exists();
    }
}
