<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Submission;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionValidationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_teacher_who_does_not_teach_the_assignments_classroom_cannot_view_the_submission(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q1', 'kssm_code' => '4.1']);
        $owningTeacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $owningTeacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $submission = Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => 'extracted',
        ]);

        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($otherTeacher)->get("/teacher/submissions/{$submission->id}");

        $response->assertForbidden();
    }
}
