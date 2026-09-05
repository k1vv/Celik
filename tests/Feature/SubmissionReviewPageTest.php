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

class SubmissionReviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_teaching_teacher_sees_each_graded_question_with_its_ai_proposal(): void
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
        $student = User::factory()->create(['role' => 'student', 'name' => 'Ah Chong']);
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
            'score' => 18,
            'max_score' => 20,
            'confidence' => 0.9,
            'needs_review' => false,
            'sampled_for_audit' => false,
        ]);
        $attempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder', 'evidence' => 'metal feels colder']);

        $response = $this->actingAs($teacher)->get("/teacher/submissions/{$submission->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('SubmissionReview')
            ->where('submission.id', $submission->id)
            ->where('submission.student_name', 'Ah Chong')
            ->where('submission.assignment_title', 'Homework 1')
            ->has('attempts', 1)
            ->where('attempts.0.id', $attempt->id)
            ->where('attempts.0.question.body', 'Explain thermal equilibrium.')
            ->where('attempts.0.raw_answer', 'Heat flows until both reach the same temperature.')
            ->where('attempts.0.score', 18)
            ->where('attempts.0.max_score', 20)
            ->where('attempts.0.teacher_score', null)
            ->where('attempts.0.diagnoses.0.dimension', 'conceptual')
            ->where('attempts.0.diagnoses.0.tag', 'metal_inherently_colder')
        );
    }
}
