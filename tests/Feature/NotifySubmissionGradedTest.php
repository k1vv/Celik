<?php

namespace Tests\Feature;

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
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifySubmissionGradedTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'http://n8n.test/webhook/submission-graded';

    private function configureWebhook(): void
    {
        config([
            'services.n8n.submission_webhook_url' => self::WEBHOOK_URL,
            'services.n8n.secret' => 'shared-secret',
        ]);
    }

    /**
     * @param  list<bool>  $needsReview  one attempt per entry, flagged accordingly
     */
    private function gradedSubmission(array $needsReview, string $joinCode = 'TEST01'): Submission
    {
        $subject = Subject::create(['name' => "Physics {$joinCode}"]);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $teacher = User::factory()->create(['role' => 'teacher', 'email' => "teacher.{$joinCode}@example.com"]);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Private Student']);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => $joinCode,
        ]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);

        $submission = Submission::create([
            'user_id' => $student->id,
            'assignment_id' => $assignment->id,
            'photo_path' => 'homework-submissions/answers.pdf',
            'extraction_status' => 'extracted',
        ]);

        foreach ($needsReview as $flagged) {
            $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Explain.']);

            Attempt::create([
                'user_id' => $student->id,
                'question_id' => $question->id,
                'submission_id' => $submission->id,
                'skill_id' => $skill->id,
                'source' => 'homework',
                'raw_answer' => 'Private answer',
                'is_correct' => true,
                'needs_review' => $flagged,
            ]);
        }

        return $submission;
    }

    public function test_posts_only_the_teacher_notification_details_to_the_webhook(): void
    {
        $this->configureWebhook();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK_URL => Http::response()]);
        $submission = $this->gradedSubmission([true]);

        NotifySubmissionGraded::dispatchSync($submission);

        // The exact payload is the contract: no student name, answer or file may leave the app.
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === self::WEBHOOK_URL
            && $request->hasHeader('X-Celik-Secret', 'shared-secret')
            && $request->data() === [
                'submission_id' => $submission->id,
                'assignment' => 'Homework 1',
                'teacher_email' => 'teacher.TEST01@example.com',
                'needs_review' => 1,
                'review_url' => route('submissions.show', $submission),
            ]);
    }

    public function test_counts_only_flagged_attempts_of_the_same_submission(): void
    {
        $this->configureWebhook();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK_URL => Http::response()]);
        $submission = $this->gradedSubmission([true, false, true]);
        $this->gradedSubmission([true, true, true], 'TEST02');

        NotifySubmissionGraded::dispatchSync($submission);

        Http::assertSent(fn (Request $request) => $request['needs_review'] === 2);
    }

    public function test_sends_nothing_when_no_webhook_url_is_configured(): void
    {
        config(['services.n8n.submission_webhook_url' => null]);
        Http::fake();
        $submission = $this->gradedSubmission([true]);

        NotifySubmissionGraded::dispatchSync($submission);

        Http::assertNothingSent();
    }

    public function test_fails_when_the_webhook_rejects_the_request_so_the_queue_can_retry(): void
    {
        $this->configureWebhook();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK_URL => Http::response(status: 403)]);
        $submission = $this->gradedSubmission([true]);

        $this->expectException(RequestException::class);

        NotifySubmissionGraded::dispatchSync($submission);
    }

    public function test_is_attempted_three_times_before_being_marked_as_failed(): void
    {
        $this->configureWebhook();
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK_URL => Http::response(status: 503)]);
        $submission = $this->gradedSubmission([true]);
        config(['queue.default' => 'database']);
        NotifySubmissionGraded::dispatch($submission);

        foreach (range(1, 3) as $attempt) {
            $this->assertDatabaseCount('failed_jobs', 0);
            $this->travel(1)->hours();
            $this->artisan('queue:work', ['--once' => true]);
        }

        Http::assertSentCount(3);
        $this->assertDatabaseCount('failed_jobs', 1);
    }
}
