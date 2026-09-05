<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeworkAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_homework_for_a_question_not_assigned_to_the_students_classroom_returns_403(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '9.2.2',
        ]);
        // No Assignment releases this question to any classroom, and this
        // student isn't enrolled anywhere - there is nothing tying them to it.
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal conducts heat away from your hand faster than wood does.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('attempts', ['question_id' => $question->id]);
    }
}
