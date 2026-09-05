<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeworkSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_typed_submission_is_recorded_as_a_homework_attempt(): void
    {
        // Diagnosis is exercised in HomeworkDiagnosisServiceTest and
        // HomeworkAttemptDiagnosisTest - this test only cares that the
        // submission itself gets persisted, independent of that outcome.
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [
                        ['functionCall' => ['name' => 'record_diagnosis', 'args' => ['correct' => true, 'confidence' => 1.0]]],
                    ]]],
                ],
            ], 200),
        ]);

        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '9.2.2',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $classroom->students()->attach($student);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal conducts heat away from your hand faster than wood does, so it feels colder even though both are at room temperature.',
        ]);

        $this->assertDatabaseHas('attempts', [
            'user_id' => $student->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'raw_answer' => 'Metal conducts heat away from your hand faster than wood does, so it feels colder even though both are at room temperature.',
        ]);
    }
}
