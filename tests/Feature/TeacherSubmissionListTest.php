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

class TeacherSubmissionListTest extends TestCase
{
    use RefreshDatabase;

    private function classroomFor(User $teacher, string $joinCode): Classroom
    {
        return Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::firstOrCreate(['name' => 'Physics'])->id,
            'name' => "Class {$joinCode}",
            'join_code' => $joinCode,
        ]);
    }

    private function submissionIn(Classroom $classroom, int $flagged = 0, array $attributes = []): Submission
    {
        $skill = Skill::firstOrCreate(['subject_id' => $classroom->subject_id, 'name' => 'Heat']);
        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain.']);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Ain Nadia']);

        $submission = Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => 'extracted',
            ...$attributes,
        ]);

        for ($i = 0; $i < $flagged; $i++) {
            Attempt::create([
                'user_id' => $student->id,
                'question_id' => $question->id,
                'submission_id' => $submission->id,
                'skill_id' => $skill->id,
                'source' => 'homework',
                'is_correct' => false,
                'needs_review' => true,
            ]);
        }

        return $submission;
    }

    public function test_the_dashboard_lists_a_submission_with_what_the_teacher_needs_to_pick_it(): void
    {
        $this->travelTo('2026-01-02 08:00:00');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $submission = $this->submissionIn($this->classroomFor($teacher, 'MINE01'), flagged: 1);

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page->where('submissions', [[
            'id' => $submission->id,
            'student_name' => 'Ain Nadia',
            'assignment_title' => 'Homework 1',
            'extraction_status' => 'extracted',
            'question_count' => 1,
            'answered_count' => 1,
            'needs_review_count' => 1,
            'teacher_validated_at' => null,
            'created_at' => '2026-01-02T08:00:00.000000Z',
        ]]));
    }

    public function test_submissions_waiting_for_review_come_first_then_unchecked_then_checked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = $this->classroomFor($teacher, 'MINE01');
        $this->travelTo('2026-01-01 08:00:00');
        $waiting = $this->submissionIn($classroom, flagged: 1);
        $this->travelTo('2026-01-02 08:00:00');
        $olderUnchecked = $this->submissionIn($classroom);
        $this->travelTo('2026-01-03 08:00:00');
        $newerUnchecked = $this->submissionIn($classroom);
        $this->travelTo('2026-01-04 08:00:00');
        $checked = $this->submissionIn($classroom, flagged: 1, attributes: ['teacher_validated_at' => now()]);

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('submissions', fn ($submissions) => $submissions->pluck('id')->all() === [
                $waiting->id, $newerUnchecked->id, $olderUnchecked->id, $checked->id,
            ])
        );
    }

    public function test_only_the_selected_classrooms_submissions_are_listed(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->submissionIn($this->classroomFor($teacher, 'MINE01'));
        $selected = $this->classroomFor($teacher, 'MINE02');
        $listed = $this->submissionIn($selected);
        $this->submissionIn($this->classroomFor(User::factory()->create(['role' => 'teacher']), 'THEIR1'));

        $response = $this->actingAs($teacher)->get("/dashboard?classroom={$selected->id}");

        $response->assertInertia(fn ($page) => $page->has('submissions', 1)->where('submissions.0.id', $listed->id));
    }

    public function test_a_teacher_without_a_classroom_gets_an_empty_list(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'teacher']))->get('/dashboard');

        $response->assertInertia(fn ($page) => $page->where('submissions', []));
    }
}
