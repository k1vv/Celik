<?php

namespace App\Services;

use App\Models\AttemptDiagnosis;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Presents teacher-authored PracticeSets for a student's own classroom,
 * with real per-student progress computed from Attempt::practice_set_id -
 * replacing the frontend's previously hardcoded practiceSets array.
 */
class PracticeSetService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(User $student, Classroom $classroom): Collection
    {
        return $classroom->practiceSets()
            ->with(['skill', 'questions'])
            ->get()
            ->map(function ($set) use ($student, $classroom) {
                $questionCount = $set->questions->count();
                $answeredCount = $set->attempts()->where('user_id', $student->id)->distinct('question_id')->count('question_id');
                $progress = $questionCount > 0 ? (int) round(100 * $answeredCount / $questionCount) : 0;

                return [
                    'id' => $set->id,
                    'title' => $set->title,
                    'subject' => $classroom->subject->name,
                    'teacher' => $classroom->teacher->name,
                    'description' => $set->description,
                    'questionCount' => $questionCount,
                    'estimatedMinutes' => $set->estimated_minutes ?? max(1, $questionCount * 2),
                    'difficulty' => $set->difficulty,
                    'dueDate' => $set->due_at,
                    'progress' => $progress,
                    'status' => $progress === 0 ? 'assigned' : ($progress >= 100 ? 'completed' : 'in_progress'),
                    'gaps' => $this->openGapsFor($student, $set->skill_id),
                    'questions' => $set->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'skill' => $question->skill->name,
                        'body' => $question->body,
                        'options' => $question->options,
                    ])->values(),
                ];
            });
    }

    /**
     * The student's own most recent open diagnoses on this skill - real
     * personalization instead of a teacher-authored static tag list, since
     * two students on the same set can be struggling with different things.
     *
     * @return array<int, array{dimension: string, tag: string}>
     */
    private function openGapsFor(User $student, int $skillId): array
    {
        return AttemptDiagnosis::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_diagnoses.attempt_id')
            ->where('attempts.user_id', $student->id)
            ->where('attempts.skill_id', $skillId)
            ->selectRaw('attempt_diagnoses.dimension, attempt_diagnoses.tag, max(attempt_diagnoses.id) as last_seen_id')
            ->groupBy('attempt_diagnoses.dimension', 'attempt_diagnoses.tag')
            ->orderByDesc('last_seen_id')
            ->limit(3)
            ->get()
            ->map(fn ($row) => ['dimension' => $row->dimension, 'tag' => $row->tag])
            ->all();
    }
}
