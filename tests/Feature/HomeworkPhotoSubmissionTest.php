<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\TopicGrounding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeworkPhotoSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Uploaded photos must never touch real dev storage during tests.
        // Storage::fake() redirects to an isolated temp directory (not
        // purely in-memory - HomeworkDiagnosisService needs a real path to
        // read the file from) that's wiped automatically after the test.
        Storage::fake('local');
    }

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

    private function fakeGeminiDiagnosis(array $args): void
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

    public function test_a_photographed_answer_is_recorded_with_no_raw_answer_and_a_stored_photo_path(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeGeminiDiagnosis([
            'correct' => false,
            'conceptual_tag' => 'metal_inherently_colder',
            'confidence' => 0.9,
        ]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'photo' => UploadedFile::fake()->image('answer.jpg'),
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertNull($attempt->raw_answer);
        $this->assertNotNull($attempt->photo_path);
        $this->assertFalse($attempt->is_correct);

        $this->assertDatabaseHas('attempt_diagnoses', [
            'attempt_id' => $attempt->id,
            'dimension' => 'conceptual',
            'tag' => 'metal_inherently_colder',
        ]);
    }

    public function test_a_pdf_photo_upload_is_accepted_the_same_way_as_an_image(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeGeminiDiagnosis(['correct' => true, 'confidence' => 0.9]);

        $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'photo' => UploadedFile::fake()->create('answer.pdf', 10, 'application/pdf'),
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($attempt->is_correct);
        $this->assertNotNull($attempt->photo_path);
    }

    public function test_submitting_neither_an_answer_nor_a_photo_fails_validation(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();

        $response = $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('attempts', ['question_id' => $question->id]);
    }
}
