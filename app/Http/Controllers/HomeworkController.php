<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Question;
use App\Services\HomeworkDiagnosisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomeworkController extends Controller
{
    public function __construct(
        private HomeworkDiagnosisService $diagnosis,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id,type,homework'],
            'answer' => ['required_without:photo', 'nullable', 'string'],
            // A photo covers both an actual photograph of handwritten work
            // and a scanned PDF - Gemini reads either directly (engine.md §5).
            'photo' => ['required_without:answer', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $question = Question::findOrFail($data['question_id']);
        $this->authorize('submitHomework', $question);

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('homework-photos');
            $rawAnswer = null;
            $diagnosis = $this->diagnosis->diagnosePhoto($question, Storage::path($photoPath));
        } else {
            $photoPath = null;
            $rawAnswer = $data['answer'];
            $diagnosis = $this->diagnosis->diagnose($question, $rawAnswer);
        }

        $attempt = Attempt::create([
            'user_id' => $request->user()->id,
            'question_id' => $question->id,
            'skill_id' => $question->skill_id,
            'source' => 'homework',
            'raw_answer' => $rawAnswer,
            'photo_path' => $photoPath,
            'is_correct' => $diagnosis['correct'],
            'score' => $diagnosis['score'],
            'max_score' => $diagnosis['max_score'],
            'confidence' => $diagnosis['confidence'],
            'needs_review' => $diagnosis['needs_review'],
            'sampled_for_audit' => $diagnosis['sampled_for_audit'],
        ]);

        if (! $diagnosis['needs_review']) {
            if ($diagnosis['conceptual']['tag']) {
                $attempt->diagnoses()->create([
                    'dimension' => 'conceptual',
                    'tag' => $diagnosis['conceptual']['tag'],
                    'evidence' => $diagnosis['conceptual']['evidence'],
                ]);
            }

            if (! $diagnosis['curriculum']['mastered'] && $diagnosis['curriculum']['kssm_code']) {
                $attempt->diagnoses()->create([
                    'dimension' => 'curriculum',
                    'tag' => $diagnosis['curriculum']['kssm_code'],
                ]);
            }

            if ($diagnosis['method']['issue']) {
                $attempt->diagnoses()->create([
                    'dimension' => 'method',
                    'tag' => $diagnosis['method']['issue'],
                    'evidence' => $diagnosis['method']['evidence'],
                ]);
            }

            if ($diagnosis['reasoning']['issue']) {
                $attempt->diagnoses()->create([
                    'dimension' => 'reasoning',
                    'tag' => $diagnosis['reasoning']['issue'],
                    'evidence' => $diagnosis['reasoning']['evidence'],
                ]);
            }
        }

        return redirect()->back()->with([
            'result' => $diagnosis['needs_review'] ? 'pending_review' : ($diagnosis['correct'] ? 'correct' : 'incorrect'),
            'homeworkDiagnosis' => $diagnosis,
        ]);
    }
}
