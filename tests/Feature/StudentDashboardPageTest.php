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

class StudentDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_real_gap_and_progress_data_instead_of_sample_data(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q']);
        $attempt = Attempt::create(['user_id' => $student->id, 'question_id' => $question->id, 'skill_id' => $skill->id, 'source' => 'homework', 'is_correct' => false, 'score' => 12, 'max_score' => 20]);
        $attempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder']);

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('StudentDashboard')
            ->where('dashboardData.stats.submitted', 1)
            ->where('dashboardData.stats.totalGaps', 1)
            ->has('dashboardData.learningGaps', 1)
            ->where('dashboardData.learningGaps.0.tag', 'metal_inherently_colder')
            ->has('dashboardData.recentWork', 1)
            ->where('dashboardData.recentWork.0.mark', 12)
            ->where('dashboardData.recentWork.0.maximum', 20)
            ->has('pastAttempts', 1)
            ->where('pastAttempts.0.question_id', $question->id)
        );
    }

    public function test_a_student_with_no_classroom_sees_empty_dashboard_data(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('StudentDashboard')
            ->where('dashboardData.stats.submitted', 0)
            ->where('dashboardData.learningGaps', [])
            ->where('dashboardData.nextDue', null)
        );
    }

    public function test_next_due_skips_an_assignment_whose_pdf_was_processed_without_matching_any_answer(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $handedIn = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1', 'due_at' => now()->addDay()]);
        Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 2', 'due_at' => now()->addWeek()]);
        Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $handedIn->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => 'extracted',
        ]);

        $response = $this->actingAs($student)->get('/student/dashboard');

        $response->assertInertia(fn ($page) => $page->where('dashboardData.nextDue.title', 'Homework 2'));
    }
}
