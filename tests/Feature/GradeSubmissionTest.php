<?php

namespace Tests\Feature;

use App\Jobs\GradeSubmission;
use App\Jobs\NotifySubmissionGraded;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GradeSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI = 'generativelanguage.googleapis.com/*';

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function functionCall(string $name, array $args): array
    {
        return ['candidates' => [['content' => ['parts' => [['functionCall' => ['name' => $name, 'args' => $args]]]]]]];
    }

    /** @return array<string, mixed> */
    private function twoAnswers(): array
    {
        return $this->functionCall('record_extracted_answers', ['answers' => [
            ['order' => 1, 'student_answer_text' => 'First answer.', 'matched' => true],
            ['order' => 2, 'student_answer_text' => 'Second answer.', 'matched' => true],
        ]]);
    }

    /** @return array<string, mixed> Low confidence, so no second-opinion call follows. */
    private function flaggedDiagnosis(): array
    {
        return $this->functionCall('record_diagnosis', ['correct' => false, 'confidence' => 0.2, 'score' => 5]);
    }

    private function pendingSubmission(): Submission
    {
        Storage::fake('local');
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);

        foreach (['First question.', 'Second question.'] as $index => $body) {
            $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => $body]);
            $assignment->questions()->attach($question->id, ['order' => $index + 1]);
        }

        return Submission::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'assignment_id' => $assignment->id,
            'photo_path' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf')->store('homework-submissions'),
            'extraction_status' => 'pending',
        ]);
    }

    private function runQueuedGrading(Submission $submission, int $times): void
    {
        config(['queue.default' => 'database']);
        GradeSubmission::dispatch($submission);

        foreach (range(1, $times) as $run) {
            $this->travel(1)->hours();
            $this->artisan('queue:work', ['--once' => true]);
        }
    }

    public function test_grades_every_answer_for_the_student_and_then_notifies_the_teacher(): void
    {
        $submission = $this->pendingSubmission();
        Http::preventStrayRequests();
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->twoAnswers())
            ->push($this->flaggedDiagnosis())
            ->push($this->flaggedDiagnosis())]);
        Queue::fake([NotifySubmissionGraded::class]);

        GradeSubmission::dispatchSync($submission);

        $this->assertSame('extracted', $submission->fresh()->extraction_status);
        $this->assertSame(
            ['First answer.', 'Second answer.'],
            $submission->attempts()->where('user_id', $submission->user_id)->orderBy('id')->pluck('raw_answer')->all()
        );
        Queue::assertPushedTimes(NotifySubmissionGraded::class, 1);
    }

    public function test_recovers_from_a_temporary_outage_midway_without_duplicating_answers(): void
    {
        $submission = $this->pendingSubmission();
        Http::preventStrayRequests();
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->twoAnswers())
            ->push($this->flaggedDiagnosis())
            ->pushFailedConnection()
            ->push($this->twoAnswers())
            ->push($this->flaggedDiagnosis())
            ->push($this->flaggedDiagnosis())]);

        $this->runQueuedGrading($submission, times: 2);

        $this->assertSame('extracted', $submission->fresh()->extraction_status);
        $this->assertDatabaseCount('attempts', 2);
    }

    public function test_is_still_being_graded_while_a_retry_is_left(): void
    {
        $submission = $this->pendingSubmission();
        Http::preventStrayRequests();
        Http::fake([self::GEMINI => Http::response(status: 503)]);

        $this->runQueuedGrading($submission, times: 2);

        $this->assertSame('pending', $submission->fresh()->extraction_status);
    }

    public function test_is_marked_failed_after_the_third_outage_with_nothing_saved_or_sent(): void
    {
        $submission = $this->pendingSubmission();
        Http::preventStrayRequests();
        Http::fake([self::GEMINI => Http::response(status: 503)]);

        $this->runQueuedGrading($submission, times: 3);

        $this->assertSame('failed', $submission->fresh()->extraction_status);
        $this->assertDatabaseEmpty('attempts');
        $this->assertDatabaseEmpty('jobs');
    }

    public function test_an_error_while_saving_leaves_no_partial_results(): void
    {
        $submission = $this->pendingSubmission();
        Http::preventStrayRequests();
        Http::fake([self::GEMINI => Http::sequence()
            ->push($this->twoAnswers())
            ->push($this->flaggedDiagnosis())
            ->push($this->flaggedDiagnosis())]);
        Attempt::creating(function (Attempt $attempt) {
            throw_if($attempt->raw_answer === 'Second answer.', RuntimeException::class, 'Database went away.');
        });

        $this->assertThrows(fn () => app()->call([new GradeSubmission($submission), 'handle']), RuntimeException::class);

        $this->assertDatabaseEmpty('attempts');
        $this->assertSame('pending', $submission->fresh()->extraction_status);
    }

    public function test_an_already_graded_submission_is_not_graded_again(): void
    {
        $submission = $this->pendingSubmission();
        $submission->update(['extraction_status' => 'extracted']);
        Http::fake();

        GradeSubmission::dispatchSync($submission);

        Http::assertNothingSent();
    }

    public function test_a_running_job_is_never_handed_to_a_second_worker(): void
    {
        $job = new GradeSubmission($this->pendingSubmission());

        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
    }
}
