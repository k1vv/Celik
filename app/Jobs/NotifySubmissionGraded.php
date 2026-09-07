<?php

namespace App\Jobs;

use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class NotifySubmissionGraded implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before each retry. */
    public array $backoff = [10, 60];

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        $url = config('services.n8n.submission_webhook_url');

        if (! $url) {
            return;
        }

        Http::withHeaders(['X-Celik-Secret' => config('services.n8n.secret')])
            ->connectTimeout(3)
            ->timeout(10)
            ->post($url, [
                'submission_id' => $this->submission->id,
                'assignment' => $this->submission->assignment->title,
                'teacher_email' => $this->submission->assignment->classroom->teacher->email,
                'needs_review' => $this->submission->attempts()->where('needs_review', true)->count(),
                'review_url' => route('submissions.show', $this->submission),
            ])
            ->throw();
    }
}
