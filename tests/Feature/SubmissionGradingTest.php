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

class SubmissionGradingTest extends TestCase
{
    use RefreshDatabase;

    private function extractionResponse(array $answers): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'record_extracted_answers', 'args' => ['answers' => $answers]]],
                ]]],
            ],
        ];
    }

    private function diagnosisResponse(array $args): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'record_diagnosis', 'args' => $args]],
                ]]],
            ],
        ];
    }

    public function test_a_submitted_pdf_is_graded_per_matched_question_and_grouped_under_one_submission(): void
    {
        Storage::fake('local');

        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        TopicGrounding::create([
            'kssm_code' => '4.1',
            'syllabus_excerpt' => 'Heat flows from hot to cold until thermal equilibrium.',
            'rubric' => 'Explain heat flow direction.',
            'tag_vocabulary' => ['conceptual' => ['metal_inherently_colder']],
        ]);
        $question = Question::create([
            'skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain thermal equilibrium.', 'kssm_code' => '4.1',
        ]);

        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->extractionResponse([
                    ['order' => 1, 'student_answer_text' => 'Heat flows until both reach the same temperature.', 'matched' => true],
                ]))
                ->push($this->diagnosisResponse(['correct' => true, 'confidence' => 0.9]))
                ->push($this->diagnosisResponse(['correct' => true, 'confidence' => 0.9])),
        ]);

        $this->actingAs($student)->post('/submissions', [
            'assignment_id' => $assignment->id,
            'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
        ]);

        $this->assertDatabaseHas('submissions', [
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'extraction_status' => 'extracted',
        ]);

        $submission = \App\Models\Submission::firstOrFail();

        $this->assertDatabaseHas('attempts', [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'source' => 'homework',
            'is_correct' => true,
            'raw_answer' => 'Heat flows until both reach the same temperature.',
        ]);
    }

    public function test_an_unmatched_answer_is_not_graded_or_persisted_as_an_attempt(): void
    {
        Storage::fake('local');

        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $question = Question::create([
            'skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain thermal equilibrium.', 'kssm_code' => '4.1',
        ]);

        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST02',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->extractionResponse([
                    ['order' => 1, 'student_answer_text' => null, 'matched' => false],
                ])),
        ]);

        $this->actingAs($student)->post('/submissions', [
            'assignment_id' => $assignment->id,
            'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
        ]);

        $this->assertDatabaseMissing('attempts', ['question_id' => $question->id]);
    }
}
