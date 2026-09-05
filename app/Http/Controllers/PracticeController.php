<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\AdaptivePracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    public function __construct(private AdaptivePracticeService $adaptive) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        $classroom = $student->classrooms()->with('subject.skills')->first();

        if (! $classroom) {
            return Inertia::render('Practice', [
                'classroom' => null,
                'question' => null,
                'masteries' => [],
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'selected_option' => ['required', 'string'],
        ]);

        $question = Question::findOrFail($data['question_id']);
        $isCorrect = $question->correct_option === $data['selected_option'];

        $this->adaptive->recordAttempt($request->user(), $question, $isCorrect);

        return redirect()->route('practice')->with([
            'result' => $isCorrect ? 'correct' : 'incorrect',
            'correct_option' => $question->correct_option,
        ]);
    }
}
