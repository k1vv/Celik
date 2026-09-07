<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitHomeworkPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_assignments_for_the_students_own_classroom_and_their_own_past_attempts(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);

        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '4.1',
        ]);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'title' => 'Homework 1',
            'due_at' => now()->addWeek(),
        ]);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $attempt = Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'raw_answer' => 'Metal is naturally colder than wood.',
            'is_correct' => false,
            'confidence' => 0.9,
            'needs_review' => false,
            'sampled_for_audit' => false,
        ]);
        $attempt->diagnoses()->create([
            'dimension' => 'conceptual',
            'tag' => 'metal_inherently_colder',
            'evidence' => 'Metal is naturally colder than wood.',
        ]);

        // A different student's attempt must not leak into this student's history.
        Attempt::create([
            'user_id' => $otherStudent->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'is_correct' => true,
            'confidence' => 1.0,
            'needs_review' => false,
            'sampled_for_audit' => false,
        ]);

        // A homework question with no Assignment for this student's classroom
        // must not appear in their assignment list.
        $unassignedQuestion = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'An unassigned homework question.',
            'kssm_code' => '4.2',
        ]);
        $otherClassroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Another Physics Class',
            'join_code' => 'PHYS02',
        ]);
        $otherAssignment = Assignment::create([
            'classroom_id' => $otherClassroom->id,
            'title' => 'Homework 1',
            'due_at' => now()->addWeek(),
        ]);
        $otherAssignment->questions()->attach($unassignedQuestion->id, ['order' => 1]);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->component('SubmitHomework')
            ->where('assignments', [
                [
                    'id' => $assignment->id,
                    'title' => 'Homework 1',
                    'due_at' => $assignment->due_at->toJSON(),
                    'question_id' => $question->id,
                    'question_body' => $question->body,
                    'kssm_code' => '4.1',
                    'skill_name' => 'Thermal Equilibrium',
                    'module_name' => null,
                    'questions' => [
                        ['id' => $question->id, 'order' => 1, 'body' => $question->body],
                    ],
                    'submitted' => true,
                ],
            ])
            ->has('pastAttempts', 1)
            ->where('pastAttempts.0.question_id', $question->id)
            ->where('pastAttempts.0.question_body', $question->body)
            ->where('pastAttempts.0.kssm_code', '4.1')
            ->where('pastAttempts.0.is_correct', false)
            ->where('pastAttempts.0.diagnoses', [
                ['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder', 'evidence' => 'Metal is naturally colder than wood.'],
            ])
        );
    }

    public function test_a_student_with_no_classroom_sees_no_assignments(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->component('SubmitHomework')
            ->where('assignments', [])
            ->where('pastAttempts', [])
            ->where('submissions', [])
        );
    }

    public function test_an_assignment_lists_every_question_in_authored_order(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['Second', 'First']);
        $assignment->questions()->sync([
            $questions[0]->id => ['order' => 2],
            $questions[1]->id => ['order' => 1],
        ]);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->where('assignments.0.questions', [
                ['id' => $questions[1]->id, 'order' => 1, 'body' => 'First'],
                ['id' => $questions[0]->id, 'order' => 2, 'body' => 'Second'],
            ])
            ->where('assignments.0.submitted', false)
        );
    }

    public function test_an_answer_to_any_question_marks_the_assignment_as_submitted(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['First', 'Second']);
        Attempt::create([
            'user_id' => $student->id,
            'question_id' => $questions[1]->id,
            'skill_id' => $questions[1]->skill_id,
            'source' => 'homework',
            'is_correct' => true,
        ]);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page->where('assignments.0.submitted', true));
    }

    public function test_a_processed_pdf_marks_the_assignment_as_submitted_even_when_no_answer_was_matched(): void
    {
        [$student, $assignment] = $this->assignmentWithQuestions(['First']);
        $this->submissionFor($student, $assignment, 'extracted');

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page->where('assignments.0.submitted', true));
    }

    public function test_a_pdf_still_being_graded_counts_as_handed_in(): void
    {
        [$student, $assignment] = $this->assignmentWithQuestions(['First']);
        $this->submissionFor($student, $assignment, 'pending');

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page->where('assignments.0.submitted', true));
    }

    public function test_a_failed_pdf_or_another_students_pdf_does_not_mark_the_assignment_as_submitted(): void
    {
        [$student, $assignment] = $this->assignmentWithQuestions(['First']);
        $this->submissionFor($student, $assignment, 'failed');
        $this->submissionFor(User::factory()->create(['role' => 'student']), $assignment, 'extracted');

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page->where('assignments.0.submitted', false));
    }

    public function test_a_pdf_does_not_mark_another_assignment_sharing_a_question_as_submitted(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['Shared']);
        $handedIn = Assignment::create(['classroom_id' => $assignment->classroom_id, 'title' => 'Homework 2']);
        $handedIn->questions()->attach($questions[0]->id, ['order' => 1]);
        Attempt::create([
            'user_id' => $student->id,
            'question_id' => $questions[0]->id,
            'submission_id' => $this->submissionFor($student, $handedIn, 'extracted')->id,
            'skill_id' => $questions[0]->skill_id,
            'source' => 'homework',
            'is_correct' => true,
        ]);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->where('assignments.0.submitted', false)
            ->where('assignments.1.submitted', true)
        );
    }

    public function test_it_lists_the_students_own_pdf_submissions_newest_first(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['First', 'Second', 'Third']);
        $this->travelTo('2026-01-01 08:00:00');
        $older = $this->submissionFor($student, $assignment, 'failed');
        $this->travelTo('2026-01-02 08:00:00');
        $newer = $this->submissionFor($student, $assignment, 'extracted');
        $this->submissionFor(User::factory()->create(['role' => 'student']), $assignment, 'extracted');

        foreach ([[$questions[0], true], [$questions[1], false]] as [$question, $needsReview]) {
            Attempt::create([
                'user_id' => $student->id,
                'question_id' => $question->id,
                'submission_id' => $newer->id,
                'skill_id' => $question->skill_id,
                'source' => 'homework',
                'is_correct' => true,
                'needs_review' => $needsReview,
            ]);
        }

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page->where('submissions', [
            [
                'id' => $newer->id,
                'assignment_title' => 'Homework 1',
                'extraction_status' => 'extracted',
                'question_count' => 3,
                'answered_count' => 2,
                'needs_review_count' => 1,
                'teacher_validated_at' => null,
                'created_at' => '2026-01-02T08:00:00.000000Z',
            ],
            [
                'id' => $older->id,
                'assignment_title' => 'Homework 1',
                'extraction_status' => 'failed',
                'question_count' => 3,
                'answered_count' => 0,
                'needs_review_count' => 0,
                'teacher_validated_at' => null,
                'created_at' => '2026-01-01T08:00:00.000000Z',
            ],
        ]));
    }

    public function test_a_teachers_mark_replaces_the_ai_score_and_the_pending_review_state(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['First']);
        $submission = $this->submissionFor($student, $assignment, 'extracted');
        $submission->update(['teacher_validated_at' => now()]);
        $this->flaggedAttempt($student, $questions[0], $submission, teacherScore: 15);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->where('pastAttempts.0.score', 15)
            ->where('pastAttempts.0.needs_review', false)
            ->where('pastAttempts.0.teacher_marked', true)
        );
    }

    public function test_an_unchecked_answer_keeps_the_ai_score_and_stays_pending_review(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['First']);
        $this->flaggedAttempt($student, $questions[0], $this->submissionFor($student, $assignment, 'extracted'));

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->where('pastAttempts.0.score', 5)
            ->where('pastAttempts.0.needs_review', true)
            ->where('pastAttempts.0.teacher_marked', false)
        );
    }

    public function test_recent_work_on_the_dashboard_shows_the_teachers_mark(): void
    {
        [$student, $assignment, $questions] = $this->assignmentWithQuestions(['First']);
        $submission = $this->submissionFor($student, $assignment, 'extracted');
        $submission->update(['teacher_validated_at' => now()]);
        $this->flaggedAttempt($student, $questions[0], $submission, teacherScore: 15);

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertInertia(fn ($page) => $page->where('dashboardData.recentWork.0.mark', 15));
    }

    private function flaggedAttempt(User $student, Question $question, Submission $submission, ?int $teacherScore = null): Attempt
    {
        return Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'submission_id' => $submission->id,
            'skill_id' => $question->skill_id,
            'source' => 'homework',
            'is_correct' => false,
            'score' => 5,
            'max_score' => 20,
            'teacher_score' => $teacherScore,
            'needs_review' => true,
        ]);
    }

    /**
     * @param  list<string>  $bodies
     * @return array{User, Assignment, list<Question>}
     */
    private function assignmentWithQuestions(array $bodies): array
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $questions = [];

        foreach ($bodies as $index => $body) {
            $questions[] = $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => $body]);
            $assignment->questions()->attach($question->id, ['order' => $index + 1]);
        }

        return [$student, $assignment, $questions];
    }

    private function submissionFor(User $student, Assignment $assignment, string $status): Submission
    {
        return Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => $status,
        ]);
    }
}
