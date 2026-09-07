<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\User;

/**
 * Turns a student's raw attempt_diagnoses history into "which learning gaps
 * are still open, and which have been cleared" - the data behind the
 * previously-hardcoded StudentDashboard.jsx stats/learningGaps/homeworkProgress.
 *
 * A (dimension, tag) gap on a skill is "open" the moment it's first
 * diagnosed. It's "cleared" the first time a later attempt on that same
 * skill no longer reproduces it - matching the same "gap surfaced again vs.
 * not" signal GapSummaryService and AdaptivePracticeService already use,
 * just replayed per-student over time instead of aggregated class-wide.
 * A cleared gap can reopen if the tag is diagnosed again later.
 */
class LearningGapService
{
    /** Attempts-since-first-seen a fixed target for an open gap's progress bar. */
    private const TARGET_ATTEMPTS_TO_CLEAR = 3;

    /**
     * @return array{
     *     stats: array{submitted: int, gapsCleared: int, totalGaps: int, practiceWaiting: int},
     *     openGaps: array<int, array<string, mixed>>,
     *     clearedGaps: array<int, array<string, mixed>>,
     *     homeworkProgress: array<int, array{label: string, value: int}>,
     *     recentWork: array<int, array<string, mixed>>,
     * }
     */
    public function forStudent(User $student): array
    {
        $attempts = Attempt::where('user_id', $student->id)
            ->whereNotNull('skill_id')
            ->with('diagnoses', 'skill')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $clearedAtAttemptId = [];
        $openGaps = [];
        $clearedGaps = [];
        $clearedCount = 0;

        foreach ($attempts->groupBy('skill_id') as $skillAttempts) {
            $open = []; // "dimension|tag" => gap state

            foreach ($skillAttempts as $index => $attempt) {
                $currentKeys = $attempt->diagnoses->map(fn ($d) => $d->dimension.'|'.$d->tag)->all();

                foreach ($open as $key => $gap) {
                    if (! in_array($key, $currentKeys, true)) {
                        $clearedAtAttemptId[$attempt->id] = ($clearedAtAttemptId[$attempt->id] ?? 0) + 1;
                        $clearedCount++;
                        $clearedGaps[] = [
                            'dimension' => $gap['dimension'],
                            'tag' => $gap['tag'],
                            'skill_name' => $gap['skill']?->name,
                            'completed' => self::TARGET_ATTEMPTS_TO_CLEAR,
                            'total' => self::TARGET_ATTEMPTS_TO_CLEAR,
                            'cleared' => true,
                            'cleared_at' => $attempt->created_at,
                        ];
                        unset($open[$key]);
                    }
                }

                foreach ($attempt->diagnoses as $diagnosis) {
                    $key = $diagnosis->dimension.'|'.$diagnosis->tag;
                    if (! isset($open[$key])) {
                        $open[$key] = ['dimension' => $diagnosis->dimension, 'tag' => $diagnosis->tag, 'skill' => $attempt->skill, 'first_index' => $index];
                    }
                }
            }

            $lastIndex = $skillAttempts->count() - 1;
            foreach ($open as $gap) {
                $attemptsSince = $lastIndex - $gap['first_index'] + 1;
                $openGaps[] = [
                    'dimension' => $gap['dimension'],
                    'tag' => $gap['tag'],
                    'skill_name' => $gap['skill']?->name,
                    'completed' => min(self::TARGET_ATTEMPTS_TO_CLEAR, $attemptsSince),
                    'total' => self::TARGET_ATTEMPTS_TO_CLEAR,
                    'cleared' => false,
                ];
            }
        }

        usort($clearedGaps, fn ($a, $b) => $b['cleared_at'] <=> $a['cleared_at']);
        $clearedGaps = array_slice($clearedGaps, 0, 3);

        $homeworkAttempts = $attempts->where('source', 'homework')->values();
        $homeworkProgress = $homeworkAttempts->values()->map(fn ($attempt, $index) => [
            'label' => 'HW'.($index + 1),
            'value' => $clearedAtAttemptId[$attempt->id] ?? 0,
        ])->all();

        $recentWork = $homeworkAttempts->reverse()->take(2)->map(fn ($attempt) => [
            'id' => $attempt->id,
            'mark' => $attempt->effectiveScore(),
            'maximum' => $attempt->max_score,
            'title' => $attempt->skill?->name,
            'date' => $attempt->created_at,
            'gap' => optional($attempt->diagnoses->first())->tag,
        ])->values()->all();

        return [
            'stats' => [
                'submitted' => $homeworkAttempts->count(),
                'gapsCleared' => $clearedCount,
                'totalGaps' => $clearedCount + count($openGaps),
                'practiceWaiting' => count($openGaps),
            ],
            'openGaps' => $openGaps,
            'clearedGaps' => $clearedGaps,
            'homeworkProgress' => $homeworkProgress,
            'recentWork' => $recentWork,
        ];
    }
}
