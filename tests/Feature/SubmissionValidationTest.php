<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Submission;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmissionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_teaching_teacher_can_override_the_ai_score_and_mark_the_submission_validated(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $question = Question::create([
            'skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain thermal equilibrium.', 'kssm_code' => '4.1',
        ]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);
        $submission = Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => 'extracted',
        ]);
        $attempt = Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'submission_id' => $submission->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'raw_answer' => 'Heat flows until both reach the same temperature.',
            'is_correct' => true,
            'score' => 15,
            'max_score' => 20,
        ]);

        $response = $this->actingAs($teacher)->post("/teacher/submissions/{$submission->id}/validate", [
            'scores' => [
                ['attempt_id' => $attempt->id, 'teacher_score' => 18],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id, 'teacher_score' => 18]);
        $this->assertNotNull($submission->fresh()->teacher_validated_at);
    }

    public function test_a_teacher_who_does_not_teach_the_assignments_classroom_cannot_validate_the_submission(): void
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
        $attempt = Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'submission_id' => $submission->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'score' => 10,
            'max_score' => 20,
        ]);

        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($otherTeacher)->post("/teacher/submissions/{$submission->id}/validate", [
            'scores' => [
                ['attempt_id' => $attempt->id, 'teacher_score' => 18],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('attempts', ['id' => $attempt->id, 'teacher_score' => null]);
    }
}
