<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeAttemptDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(): Question
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);

        return Question::create([
            'skill_id' => $skill->id,
            'body' => 'A metal spoon and a wooden spoon are left on a table overnight. Why does the metal spoon feel colder?',
            'options' => ['A', 'B'],
            'correct_option' => 'A',
            'distractor_map' => ['B' => 'metal_inherently_colder'],
            'kssm_code' => '9.2.2',
        ]);
    }

    public function test_a_wrong_answer_records_a_conceptual_and_curriculum_diagnosis(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();

        $this->actingAs($student)->post('/practice', [
            'question_id' => $question->id,
            'selected_option' => 'B',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'conceptual',
            'tag' => 'metal_inherently_colder',
        ]);

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'curriculum',
            'tag' => '9.2.2',
        ]);
    }

    public function test_a_correct_answer_records_no_diagnosis(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();

        $this->actingAs($student)->post('/practice', [
            'question_id' => $question->id,
            'selected_option' => 'A',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertDatabaseMissing('attempt_diagnoses', ['attempt_id' => $attempt->id]);
    }
}
