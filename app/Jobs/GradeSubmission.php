<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Submission;
use App\Services\HomeworkDiagnosisService;
use App\Services\HomeworkPdfExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GradeSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> Seconds to wait before each retry. */
    public array $backoff = [10, 60];

    /** One PDF needs up to two AI calls per question, plus one to read it. */
    public int $timeout = 600;

    public function __construct(public Submission $submission) {}

    public function handle(HomeworkPdfExtractionService $extraction, HomeworkDiagnosisService $diagnosis): void
    {
        if ($this->submission->extraction_status !== 'pending') {
            return;
        }

        $gradedAnswers = $this->gradeAnswers($extraction, $diagnosis);

        DB::transaction(function () use ($gradedAnswers) {
            foreach ($gradedAnswers as ['question' => $question, 'answer' => $answer, 'diagnosis' => $diagnosis]) {
                $this->saveAttempt($question, $answer, $diagnosis);
            }

            $this->submission->update(['extraction_status' => 'extracted']);
        });

        NotifySubmissionGraded::dispatch($this->submission);
    }

    public function failed(?Throwable $exception): void
    {
        Submission::whereKey($this->submission->id)
            ->where('extraction_status', 'pending')
            ->update(['extraction_status' => 'failed']);
    }

    /**
     * Grades every answer before anything is saved, so an AI outage partway
     * through leaves no half-graded submission behind.
     *
     * @return list<array{question: Question, answer: string, diagnosis: array<string, mixed>}>
     */
    private function gradeAnswers(HomeworkPdfExtractionService $extraction, HomeworkDiagnosisService $diagnosis): array
    {
        /** @var Assignment $assignment */
        $assignment = $this->submission->assignment;
        $questions = $assignment->questions;
        $questionsByOrder = $questions->keyBy(fn (Question $question) => $question->pivot->order);
        $knownQuestions = $questions->map(fn (Question $question) => [
            'order' => $question->pivot->order,
            'question_text' => $question->body,
        ])->all();

        $gradedAnswers = [];

        foreach ($extraction->extractAnswers(Storage::path($this->submission->photo_path), $knownQuestions) as $answer) {
            $isAnswered = $answer['matched'] && filled($answer['student_answer_text']);
            $question = $isAnswered ? $questionsByOrder->get($answer['order']) : null;

            if (! $question) {
                continue;
            }

            $gradedAnswers[] = [
                'question' => $question,
                'answer' => $answer['student_answer_text'],
                'diagnosis' => $diagnosis->diagnose($question, $answer['student_answer_text']),
            ];
        }

        return $gradedAnswers;
    }

    /** @param  array<string, mixed>  $diagnosis */
    private function saveAttempt(Question $question, string $answer, array $diagnosis): void
    {
        $attempt = Attempt::create([
            'user_id' => $this->submission->user_id,
            'question_id' => $question->id,
            'submission_id' => $this->submission->id,
            'skill_id' => $question->skill_id,
            'source' => 'homework',
            'raw_answer' => $answer,
            'is_correct' => $diagnosis['correct'],
            'score' => $diagnosis['score'],
            'max_score' => $diagnosis['max_score'],
            'confidence' => $diagnosis['confidence'],
            'needs_review' => $diagnosis['needs_review'],
            'sampled_for_audit' => $diagnosis['sampled_for_audit'],
        ]);

        if ($diagnosis['needs_review']) {
            return;
        }

        foreach (['conceptual', 'method', 'reasoning'] as $dimension) {
            $tagKey = $dimension === 'conceptual' ? 'tag' : 'issue';
            $tag = $diagnosis[$dimension][$tagKey] ?? null;

            if ($tag) {
                $attempt->diagnoses()->create([
                    'dimension' => $dimension,
                    'tag' => $tag,
                    'evidence' => $diagnosis[$dimension]['evidence'] ?? null,
                ]);
            }
        }

        if (! $diagnosis['curriculum']['mastered'] && $diagnosis['curriculum']['kssm_code']) {
            $attempt->diagnoses()->create([
                'dimension' => 'curriculum',
                'tag' => $diagnosis['curriculum']['kssm_code'],
            ]);
        }
    }
}
