<?php

namespace Tests\Feature;

use App\Jobs\GradeSubmission;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\TopicGrounding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

        $submission = Submission::firstOrFail();

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

    public function test_the_teacher_notification_is_sent_once_after_grading_has_flagged_answers(): void
    {
        Storage::fake('local');

        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $question = Question::create([
            'skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain thermal equilibrium.',
        ]);

        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST03',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        config(['services.n8n' => ['submission_webhook_url' => 'http://n8n.test/webhook', 'secret' => 'shared-secret']]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->extractionResponse([
                    ['order' => 1, 'student_answer_text' => 'Not sure.', 'matched' => true],
                ]))
                ->push($this->diagnosisResponse(['correct' => false, 'confidence' => 0.2])),
            'n8n.test/*' => Http::response(),
        ]);

        $this->actingAs($student)->post('/submissions', [
            'assignment_id' => $assignment->id,
            'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $submission = Submission::firstOrFail();

        $notifications = Http::recorded(fn (Request $request) => $request->url() === 'http://n8n.test/webhook');
        $this->assertCount(1, $notifications);
        $this->assertSame($submission->id, $notifications->first()[0]['submission_id']);
        $this->assertSame(1, $notifications->first()[0]['needs_review']);
    }

    public function test_a_matched_answer_without_text_is_skipped(): void
    {
        Storage::fake('local');
        [$student, $assignment] = $this->releasedAssignment(['Explain thermal equilibrium.']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->extractionResponse([
            ['order' => 1, 'student_answer_text' => null, 'matched' => true],
        ]))]);

        $this->submitPdf($student, $assignment)->assertSessionHasNoErrors()->assertRedirect('/student/homework/submit');

        $this->assertDatabaseEmpty('attempts');
    }

    public function test_the_student_returns_to_the_page_knowing_which_submission_was_just_made(): void
    {
        Storage::fake('local');
        [$student, $assignment] = $this->releasedAssignment(['Explain thermal equilibrium.']);
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->extractionResponse([]))]);

        $response = $this->actingAs($student)
            ->from('/student/homework/submit')
            ->followingRedirects()
            ->post('/submissions', [
                'assignment_id' => $assignment->id,
                'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
            ]);

        $response->assertInertia(fn ($page) => $page
            ->component('SubmitHomework')
            ->where('flash.submission_id', Submission::firstOrFail()->id)
        );
    }

    public function test_handing_in_a_pdf_queues_grading_without_waiting_for_the_ai(): void
    {
        Storage::fake('local');
        [$student, $assignment] = $this->releasedAssignment(['Explain thermal equilibrium.']);
        Http::preventStrayRequests();
        Queue::fake([GradeSubmission::class]);

        $this->submitPdf($student, $assignment)->assertRedirect('/student/homework/submit');

        $submission = Submission::firstOrFail();
        $this->assertSame('pending', $submission->extraction_status);
        Queue::assertPushedTimes(GradeSubmission::class, 1);
        Queue::assertPushed(GradeSubmission::class, fn (GradeSubmission $job) => $job->submission->is($submission));
    }

    private function submitPdf(User $student, Assignment $assignment): TestResponse
    {
        return $this->actingAs($student)
            ->from('/student/homework/submit')
            ->post('/submissions', [
                'assignment_id' => $assignment->id,
                'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
            ]);
    }

    /**
     * @param  list<string>  $bodies
     * @return array{User, Assignment}
     */
    private function releasedAssignment(array $bodies): array
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST04',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);

        foreach ($bodies as $index => $body) {
            $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => $body]);
            $assignment->questions()->attach($question->id, ['order' => $index + 1]);
        }

        return [$student, $assignment];
    }
}
