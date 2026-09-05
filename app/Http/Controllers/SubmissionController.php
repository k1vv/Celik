<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Submission;
use App\Services\HomeworkDiagnosisService;
use App\Services\HomeworkPdfExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    public function __construct(
        private HomeworkPdfExtractionService $extraction,
        private HomeworkDiagnosisService $diagnosis,
    ) {}

    public function show(Submission $submission): Response
    {
        $this->authorize('validate', $submission);

        $submission->load(['user', 'assignment', 'attempts.question', 'attempts.diagnoses']);

        return Inertia::render('SubmissionReview', [
            'submission' => [
                'id' => $submission->id,
                'student_name' => $submission->user->name,
                'assignment_title' => $submission->assignment->title,
                'extraction_status' => $submission->extraction_status,
                'teacher_validated_at' => $submission->teacher_validated_at,
            ],
            'attempts' => $submission->attempts->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'question' => [
                    'id' => $attempt->question->id,
                    'body' => $attempt->question->body,
                ],
                'raw_answer' => $attempt->raw_answer,
                'is_correct' => $attempt->is_correct,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'teacher_score' => $attempt->teacher_score,
                'confidence' => $attempt->confidence,
                'needs_review' => $attempt->needs_review,
                'sampled_for_audit' => $attempt->sampled_for_audit,
                'diagnoses' => $attempt->diagnoses->map(fn ($diagnosis) => [
                    'dimension' => $diagnosis->dimension,
                    'tag' => $diagnosis->tag,
                    'evidence' => $diagnosis->evidence,
                ])->values(),
            ])->values(),
        ]);
    }

    public function validate(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorize('validate', $submission);

        $data = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*.attempt_id' => ['required', 'integer', 'exists:attempts,id'],
            'scores.*.teacher_score' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['scores'] as $row) {
            $submission->attempts()->whereKey($row['attempt_id'])->update(['teacher_score' => $row['teacher_score']]);
        }

        $submission->update(['teacher_validated_at' => now()]);

        return redirect()->route('submissions.show', $submission);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $assignment = Assignment::findOrFail($data['assignment_id']);
        $this->authorize('create', [Submission::class, $assignment]);

        $path = $request->file('pdf')->store('homework-submissions');

        $submission = Submission::create([
            'user_id' => $request->user()->id,
            'assignment_id' => $assignment->id,
            'photo_path' => $path,
            'extraction_status' => 'pending',
        ]);

        $questions = $assignment->questions()->orderBy('assignment_questions.order')->get();
        $questionsByOrder = $questions->keyBy(fn ($question) => $question->pivot->order);
        $knownQuestions = $questions->map(fn ($question) => [
            'order' => $question->pivot->order,
            'question_text' => $question->body,
        ])->all();

        $answers = $this->extraction->extractAnswers(Storage::path($path), $knownQuestions);
        $submission->update(['extraction_status' => 'extracted']);

        foreach ($answers as $answer) {
            if (! $answer['matched']) {
                continue;
            }

            $question = $questionsByOrder->get($answer['order']);

            if (! $question) {
                continue;
            }

            $diagnosis = $this->diagnosis->diagnose($question, $answer['student_answer_text']);

            $attempt = Attempt::create([
                'user_id' => $request->user()->id,
                'question_id' => $question->id,
                'submission_id' => $submission->id,
                'skill_id' => $question->skill_id,
                'source' => 'homework',
                'raw_answer' => $answer['student_answer_text'],
                'is_correct' => $diagnosis['correct'],
                'score' => $diagnosis['score'],
                'max_score' => $diagnosis['max_score'],
                'confidence' => $diagnosis['confidence'],
                'needs_review' => $diagnosis['needs_review'],
                'sampled_for_audit' => $diagnosis['sampled_for_audit'],
            ]);

            if ($diagnosis['needs_review']) {
                continue;
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

        return redirect()->back()->with(['submission_id' => $submission->id]);
    }
}
