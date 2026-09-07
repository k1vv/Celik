<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Submission;
use App\Services\LearningGapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'assignments' => $this->assignmentsFor($request, $classroom),
            'pastAttempts' => $this->pastHomeworkAttemptsFor($request),
        ]);
    }

    public function submitHomework(Request $request): Response
    {
        $classroom = $this->classroomFor($request);

        return Inertia::render('SubmitHomework', [
            'classroom' => $classroom,
            'assignments' => $this->assignmentsFor($request, $classroom),
            'pastAttempts' => $this->pastHomeworkAttemptsFor($request),
            'submissions' => $this->submissionsFor($request),
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
    private function assignmentsFor(Request $request, ?array $classroom): array
    {
        if (! $classroom) {
            return [];
        }

        $answeredQuestionIds = $this->answeredHomeworkQuestionIds($request);

        return Assignment::query()
            ->where('classroom_id', $classroom['id'])
            ->with('questions.skill.module')
            ->withExists(['submissions as has_handed_in_pdf' => fn ($query) => $this->handedInBy($query, $request)])
            ->get()
            ->map(function (Assignment $assignment) use ($answeredQuestionIds) {
                // The question_* keys describe the first question, for pages
                // that show one topic per assignment.
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
                    'questions' => $assignment->questions->map(fn (Question $question) => [
                        'id' => $question->id,
                        'order' => $question->pivot->order,
                        'body' => $question->body,
                    ])->all(),
                    'submitted' => $assignment->has_handed_in_pdf
                        || $assignment->questions->pluck('id')->intersect($answeredQuestionIds)->isNotEmpty(),
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

        $answeredQuestionIds = $this->answeredHomeworkQuestionIds($request);

        $assignment = Assignment::query()
            ->where('classroom_id', $classroom['id'])
            ->whereDoesntHave('questions', fn ($query) => $query->whereIn('questions.id', $answeredQuestionIds))
            ->whereDoesntHave('submissions', fn ($query) => $this->handedInBy($query, $request))
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

    /**
     * Questions answered one at a time. Answers read from a PDF are left out:
     * questions are shared between assignments, and a PDF only hands in its own.
     *
     * @return Collection<int, int>
     */
    private function answeredHomeworkQuestionIds(Request $request): Collection
    {
        return $request->user()->attempts()
            ->where('source', 'homework')
            ->whereNull('submission_id')
            ->pluck('question_id');
    }

    /**
     * Limits a submissions query to PDFs this student has handed in. A failed
     * one still has to be handed in again, so it does not count.
     *
     * @param  Builder<Submission>  $query
     */
    private function handedInBy(Builder $query, Request $request): void
    {
        $query->where('user_id', $request->user()->id)->whereIn('extraction_status', ['pending', 'extracted']);
    }

    /** @return array<int, array<string, mixed>> */
    private function submissionsFor(Request $request): array
    {
        return Submission::query()
            ->where('user_id', $request->user()->id)
            ->with(['assignment' => fn ($query) => $query->withCount('questions')])
            ->withCount([
                'attempts',
                'attempts as needs_review_count' => fn ($query) => $query->where('needs_review', true),
            ])
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (Submission $submission) => [
                'id' => $submission->id,
                'assignment_title' => $submission->assignment->title,
                'extraction_status' => $submission->extraction_status,
                'question_count' => $submission->assignment->questions_count,
                'answered_count' => $submission->attempts_count,
                'needs_review_count' => $submission->needs_review_count,
                'teacher_validated_at' => $submission->teacher_validated_at,
                'created_at' => $submission->created_at,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function pastHomeworkAttemptsFor(Request $request): array
    {
        return $request->user()->attempts()
            ->where('source', 'homework')
            ->with('question', 'diagnoses', 'submission')
            ->latest()
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => $attempt->id,
                'question_id' => $attempt->question_id,
                'question_body' => $attempt->question->body,
                'kssm_code' => $attempt->question->kssm_code,
                'raw_answer' => $attempt->raw_answer,
                'is_correct' => $attempt->is_correct,
                'score' => $attempt->effectiveScore(),
                'max_score' => $attempt->max_score,
                'needs_review' => $attempt->needs_review && ! $attempt->isTeacherMarked(),
                'teacher_marked' => $attempt->isTeacherMarked(),
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
