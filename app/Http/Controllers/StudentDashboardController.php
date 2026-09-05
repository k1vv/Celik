<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Services\LearningGapService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function __construct(private LearningGapService $learningGaps) {}

    public function index(Request $request): Response
    {
        $classroom = $this->classroomFor($request);

        return Inertia::render('StudentDashboard', [
            'classroom' => $classroom,
            'dashboardData' => $this->dashboardDataFor($request, $classroom),
            'assignments' => $this->assignmentsFor($classroom),
            'pastAttempts' => $this->pastHomeworkAttemptsFor($request),
        ]);
    }

    public function submitHomework(Request $request): Response
    {
        $classroom = $this->classroomFor($request);

        return Inertia::render('SubmitHomework', [
            'classroom' => $classroom,
            'assignments' => $this->assignmentsFor($classroom),
            'pastAttempts' => $this->pastHomeworkAttemptsFor($request),
        ]);
    }

    /** @return array{id: int, name: string, subject: string, teacher: string}|null */
    private function classroomFor(Request $request): ?array
    {
        $classroom = $request->user()->classrooms()->with('subject', 'teacher')->first();

        if (! $classroom) {
            return null;
        }

        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'subject' => $classroom->subject->name,
            'teacher' => $classroom->teacher->name,
        ];
    }

    /**
     * Assignments (teacher-released homework) for the student's own
     * classroom - scoped to the same single classroom classroomFor()
     * already resolved, matching this controller's existing one-classroom
     * assumption rather than introducing new multi-classroom logic.
     *
     * @param  array{id: int, name: string, subject: string}|null  $classroom
     * @return array<int, array<string, mixed>>
     */
    private function assignmentsFor(?array $classroom): array
    {
        if (! $classroom) {
            return [];
        }

        return Assignment::query()
            ->where('classroom_id', $classroom['id'])
            ->with('questions.skill.module')
            ->get()
            ->map(function (Assignment $assignment) {
                // Single-question assignments only, for now - the multi-
                // question PDF flow (§ Assignment now bundles many Questions)
                // has its own Submission-based page coming later; this page
                // still reflects "the first/only bundled question" until
                // that lands, per architecture/PROGRESS.md.
                $question = $assignment->questions->first();

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'due_at' => $assignment->due_at,
                    'question_id' => $question?->id,
                    'question_body' => $question?->body,
                    'kssm_code' => $question?->kssm_code,
                    'skill_name' => $question?->skill->name,
                    'module_name' => $question?->skill->module?->name,
                ];
            })
            ->all();
    }

    /**
     * @param  array{id: int, name: string, subject: string}|null  $classroom
     * @return array<string, mixed>
     */
    private function dashboardDataFor(Request $request, ?array $classroom): array
    {
        $gaps = $this->learningGaps->forStudent($request->user());

        return [
            'stats' => $gaps['stats'],
            'learningGaps' => [...$gaps['openGaps'], ...$gaps['clearedGaps']],
            'homeworkProgress' => $gaps['homeworkProgress'],
            'recentWork' => $gaps['recentWork'],
            'nextPractice' => $this->nextPracticeFor($gaps['openGaps']),
            'nextDue' => $this->nextDueFor($request, $classroom),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $openGaps
     * @return array<string, mixed>|null
     */
    private function nextPracticeFor(array $openGaps): ?array
    {
        if (empty($openGaps)) {
            return null;
        }

        $gap = $openGaps[0];

        return [
            'code' => strtoupper($gap['dimension']),
            'title' => 'Practice built for '.str_replace('_', ' ', $gap['tag']),
            'description' => 'Targeted to '.($gap['skill_name'] ?? 'this skill').' — practice adapts to your mastery after every answer.',
            'completed' => $gap['completed'],
            'questions' => $gap['total'],
            'minutes' => max(1, $gap['total'] * 2),
        ];
    }

    /**
     * @param  array{id: int, name: string, subject: string}|null  $classroom
     * @return array{title: string, topic: ?string, due: string}|null
     */
    private function nextDueFor(Request $request, ?array $classroom): ?array
    {
        if (! $classroom) {
            return null;
        }

        $submittedQuestionIds = $request->user()->attempts()->where('source', 'homework')->pluck('question_id');

        $assignment = Assignment::query()
            ->where('classroom_id', $classroom['id'])
            ->whereDoesntHave('questions', fn ($query) => $query->whereIn('questions.id', $submittedQuestionIds))
            ->with('questions.skill')
            ->orderBy('due_at')
            ->first();

        if (! $assignment) {
            return null;
        }

        return [
            'title' => $assignment->title,
            'topic' => $assignment->questions->first()?->skill->name,
            'due' => $assignment->due_at?->format('l, g:i A') ?? 'No due date set',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function pastHomeworkAttemptsFor(Request $request): array
    {
        return $request->user()->attempts()
            ->where('source', 'homework')
            ->with('question', 'diagnoses')
            ->latest()
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'question_id' => $attempt->question_id,
                'question_body' => $attempt->question->body,
                'kssm_code' => $attempt->question->kssm_code,
                'raw_answer' => $attempt->raw_answer,
                'is_correct' => $attempt->is_correct,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'needs_review' => $attempt->needs_review,
                'sampled_for_audit' => $attempt->sampled_for_audit,
                'created_at' => $attempt->created_at,
                'diagnoses' => $attempt->diagnoses->map(fn ($diagnosis) => [
                    'dimension' => $diagnosis->dimension,
                    'tag' => $diagnosis->tag,
                    'evidence' => $diagnosis->evidence,
                ])->all(),
            ])
            ->all();
    }
}
