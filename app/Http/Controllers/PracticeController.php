<?php

namespace App\Http\Controllers;

use App\Models\PracticeSet;
use App\Models\Question;
use App\Services\AdaptivePracticeService;
use App\Services\DiagnosisService;
use App\Services\PracticeSetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    public function __construct(
        private AdaptivePracticeService $adaptive,
        private DiagnosisService $diagnosis,
        private PracticeSetService $practiceSets,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $classroom = $student->classrooms()->with('subject.skills')->first();

        if (! $classroom) {
            return Inertia::render('Practice', [
                'classroom' => null,
                'question' => null,
                'masteries' => [],
                'practiceSets' => [],
            ]);
        }

        $question = $this->adaptive->nextQuestionFor($student, $classroom);
        $masteries = $student->masteries()
            ->whereIn('skill_id', $classroom->subject->skills->pluck('id'))
            ->with('skill')
            ->get()
            ->map(fn ($m) => [
                'skill' => $m->skill->name,
                'mastery_score' => round($m->mastery_score, 2),
            ]);

        return Inertia::render('Practice', [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'subject' => $classroom->subject->name,
            ],
            'question' => $question ? [
                'id' => $question->id,
                'skill' => $question->skill->name,
                'body' => $question->body,
                'options' => $question->options,
                'difficulty' => $question->difficulty,
            ] : null,
            'masteries' => $masteries,
            'practiceSets' => $this->practiceSets->forStudent($student, $classroom),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'selected_option' => ['required', 'string'],
            'practice_set_id' => ['nullable', 'integer', 'exists:practice_sets,id'],
        ]);

        $question = Question::findOrFail($data['question_id']);
        $this->authorize('attemptQuiz', $question);

        $practiceSet = null;
        if (! empty($data['practice_set_id'])) {
            $practiceSet = PracticeSet::findOrFail($data['practice_set_id']);
            $this->authorize('attempt', $practiceSet);
        }

        $diagnosis = $this->diagnosis->diagnose($question, $data['selected_option']);

        $attempt = $this->adaptive->recordAttempt($request->user(), $question, $diagnosis['correct'], $practiceSet?->id);

        if ($diagnosis['conceptual']['tag']) {
            $attempt->diagnoses()->create([
                'dimension' => 'conceptual',
                'tag' => $diagnosis['conceptual']['tag'],
            ]);
        }

        if (! $diagnosis['curriculum']['mastered'] && $diagnosis['curriculum']['kssm_code']) {
            $attempt->diagnoses()->create([
                'dimension' => 'curriculum',
                'tag' => $diagnosis['curriculum']['kssm_code'],
            ]);
        }

        return redirect()->route('practice')->with([
            'result' => $diagnosis['correct'] ? 'correct' : 'incorrect',
            'correct_option' => $question->correct_option,
        ]);
    }
}
