<?php

namespace App\Http\Controllers;

use App\Jobs\GradeSubmission;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
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

        GradeSubmission::dispatch($submission);

        return redirect()->back()->with(['submission_id' => $submission->id]);
    }
}
