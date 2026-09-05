<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\TopicGrounding;
use App\Models\User;
use App\Services\HomeworkDiagnosisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class HomeworkAttemptDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(): Question
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);

        TopicGrounding::create([
            'kssm_code' => '9.2.2',
            'syllabus_excerpt' => 'Heat is the transfer of thermal energy from a hotter to a cooler object.',
            'rubric' => 'Full marks require distinguishing heat from temperature.',
            'tag_vocabulary' => ['conceptual' => ['metal_inherently_colder']],
        ]);

        return Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '9.2.2',
        ]);
    }

    /** Releases $question to $student's classroom, per QuestionPolicy::submitHomework. */
    private function assignToStudent(Question $question, User $student): void
    {
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $question->skill->subject_id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $classroom->students()->attach($student);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);
    }

    private function fakeDiagnosis(array $args): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [
                        ['functionCall' => ['name' => 'record_diagnosis', 'args' => $args]],
                    ]]],
                ],
            ], 200),
        ]);
    }

    public function test_a_trusted_gap_updates_the_attempt_and_writes_diagnoses(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis([
            'correct' => false,
            'conceptual_tag' => 'metal_inherently_colder',
            'conceptual_evidence' => 'Metal is just colder than wood.',
            'reasoning_issue' => 'Does not explain the direction of heat transfer.',
            'confidence' => 0.82,
        ]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal is just colder than wood.',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertFalse($attempt->is_correct);
        $this->assertFalse($attempt->needs_review);
        $this->assertSame(0.82, $attempt->confidence);

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'conceptual',
            'tag' => 'metal_inherently_colder',
            'evidence' => 'Metal is just colder than wood.',
        ]);

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'curriculum',
            'tag' => '9.2.2',
        ]);

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'reasoning',
            'tag' => 'Does not explain the direction of heat transfer.',
        ]);
    }

    public function test_a_low_confidence_diagnosis_is_flagged_and_writes_no_diagnoses(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis([
            'correct' => false,
            'conceptual_tag' => 'metal_inherently_colder',
            'confidence' => 0.35,
        ]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Not sure, maybe metal is colder?',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($attempt->needs_review);
        $this->assertDatabaseMissing('attempt_diagnoses', ['attempt_id' => $attempt->id]);
    }

    public function test_a_sampled_attempt_is_persisted_as_sampled_for_audit(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis(['correct' => true, 'confidence' => 0.9]);

        $service = Mockery::mock(HomeworkDiagnosisService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('rollForAudit')->andReturn(true);
        $this->app->instance(HomeworkDiagnosisService::class, $service);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal conducts heat away from the hand faster than wood, making it feel colder.',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($attempt->sampled_for_audit);
        $this->assertTrue($attempt->is_correct);
    }

    public function test_an_unsampled_attempt_is_not_persisted_as_sampled_for_audit(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis(['correct' => true, 'confidence' => 0.9]);

        $service = Mockery::mock(HomeworkDiagnosisService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('rollForAudit')->andReturn(false);
        $this->app->instance(HomeworkDiagnosisService::class, $service);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal conducts heat away from the hand faster than wood, making it feel colder.',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertFalse($attempt->sampled_for_audit);
    }

    public function test_a_diagnosis_with_a_rubric_mark_persists_the_score_on_the_attempt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis([
            'correct' => false,
            'conceptual_tag' => 'metal_inherently_colder',
            'confidence' => 0.82,
            'score' => 14,
        ]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal is just colder than wood.',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertSame(14, $attempt->score);
        $this->assertSame(20, $attempt->max_score);
    }

    public function test_a_correct_answer_records_no_diagnosis(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis(['correct' => true, 'confidence' => 0.9]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal conducts heat away from the hand faster than wood, making it feel colder.',
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($attempt->is_correct);
        $this->assertDatabaseMissing('attempt_diagnoses', ['attempt_id' => $attempt->id]);
    }
}
