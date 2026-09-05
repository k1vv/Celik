<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardGapSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_shows_a_class_wide_gap_breakdown_by_dimension_and_tag(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'ABC123',
        ]);

        $question = Question::create([
            'skill_id' => $skill->id,
            'body' => 'Why does the metal spoon feel colder?',
            'options' => ['A', 'B'],
            'correct_option' => 'A',
            'distractor_map' => ['B' => 'metal_inherently_colder'],
            'kssm_code' => '9.2.2',
            'difficulty' => 3,
        ]);

        $students = User::factory()->count(3)->create(['role' => 'student']);
        $classroom->students()->attach($students->pluck('id'));

        // Two of the three students share the same misconception.
        foreach ($students->take(2) as $student) {
            $attempt = Attempt::create([
                'user_id' => $student->id,
                'question_id' => $question->id,
                'skill_id' => $skill->id,
                'is_correct' => false,
                'difficulty_at_attempt' => $question->difficulty,
                'mastery_before' => 0.3,
                'mastery_after' => 0.2,
            ]);

            $attempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder']);
            $attempt->diagnoses()->create(['dimension' => 'curriculum', 'tag' => '9.2.2']);
        }

        $response = $this->actingAs($teacher)->get('/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('gapSummary', [
                ['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder', 'student_count' => 2],
                ['dimension' => 'curriculum', 'tag' => '9.2.2', 'student_count' => 2],
            ])
        );
    }
}
