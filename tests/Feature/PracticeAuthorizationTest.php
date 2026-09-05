<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_quiz_answer_for_a_question_outside_the_students_classroom_subject_returns_403(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $question = Question::create([
            'skill_id' => $skill->id,
            'body' => 'A metal spoon and a wooden spoon are left on a table overnight. Why does the metal spoon feel colder?',
            'options' => ['A', 'B'],
            'correct_option' => 'A',
            'distractor_map' => ['B' => 'metal_inherently_colder'],
            'kssm_code' => '9.2.2',
        ]);
        // This student isn't enrolled in any classroom for this question's
        // subject - nothing ties them to it.
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->post('/practice', [
            'question_id' => $question->id,
            'selected_option' => 'A',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('attempts', ['question_id' => $question->id]);
    }
}
