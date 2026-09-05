<?php

namespace App\Services;

use App\Models\AttemptDiagnosis;
use Illuminate\Support\Collection;

/**
 * Aggregates attempt_diagnoses into the class-wide "where is the class
 * struggling" breakdown described in engine.md section 6: how many
 * distinct students carry each (dimension, tag) gap.
 */
class GapSummaryService
{
    /**
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, array{dimension: string, tag: string, student_count: int}>
     */
    public function forStudents(Collection $studentIds): Collection
    {
        return AttemptDiagnosis::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_diagnoses.attempt_id')
            ->whereIn('attempts.user_id', $studentIds)
            ->selectRaw('attempt_diagnoses.dimension, attempt_diagnoses.tag, count(distinct attempts.user_id) as student_count')
            ->groupBy('attempt_diagnoses.dimension', 'attempt_diagnoses.tag')
            ->orderByDesc('student_count')
            ->orderBy('attempt_diagnoses.dimension')
            ->orderBy('attempt_diagnoses.tag')
            ->get()
            ->map(fn ($row) => [
                'dimension' => $row->dimension,
                'tag' => $row->tag,
                'student_count' => (int) $row->student_count,
            ]);
    }
}
