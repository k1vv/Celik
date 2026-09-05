<?php

namespace App\Services;

use App\Models\AttemptDiagnosis;
use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Aggregates attempt_diagnoses into the class-wide "where is the class
 * struggling" breakdown described in engine.md section 6: how many
 * distinct students carry each (dimension, tag) gap.
 *
 * Per engine.md §8, when two gapped skills are linked by a prerequisite
 * edge, the upstream skill's gap is annotated as the root cause and the
 * downstream skill's gap as a symptom, instead of two unrelated-looking
 * rows - the same idea as differential diagnosis: trace symptoms back along
 * a dependency graph to the actual cause.
 */
class GapSummaryService
{
    /**
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, array{
     *     dimension: string,
     *     tag: string,
     *     student_count: int,
     *     skill_id: ?int,
     *     relation: ?string,
     *     related_skill_id: ?int,
     * }>
     */
    public function forStudents(Collection $studentIds): Collection
    {
        $rows = AttemptDiagnosis::query()
            ->join('attempts', 'attempts.id', '=', 'attempt_diagnoses.attempt_id')
            ->whereIn('attempts.user_id', $studentIds)
            ->selectRaw('attempt_diagnoses.dimension, attempt_diagnoses.tag, attempts.skill_id, count(distinct attempts.user_id) as student_count')
            ->groupBy('attempt_diagnoses.dimension', 'attempt_diagnoses.tag', 'attempts.skill_id')
            ->orderByDesc('student_count')
            ->orderBy('attempt_diagnoses.dimension')
            ->orderBy('attempt_diagnoses.tag')
            ->get()
            ->map(fn ($row) => [
                'dimension' => $row->dimension,
                'tag' => $row->tag,
                'student_count' => (int) $row->student_count,
                'skill_id' => $row->skill_id,
                'relation' => null,
                'related_skill_id' => null,
            ]);

        return $this->annotateRootCauses($rows);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function annotateRootCauses(Collection $rows): Collection
    {
        $gappedSkillIds = $rows->pluck('skill_id')->filter()->unique();

        if ($gappedSkillIds->count() < 2) {
            return $rows;
        }

        $skills = Skill::with('prerequisites')->whereIn('id', $gappedSkillIds)->get()->keyBy('id');

        return $rows->map(function (array $row) use ($skills, $gappedSkillIds) {
            $skill = $row['skill_id'] ? $skills->get($row['skill_id']) : null;

            if (! $skill) {
                return $row;
            }

            $gappedPrerequisite = $skill->prerequisites->first(
                fn ($prerequisite) => $gappedSkillIds->contains($prerequisite->id)
            );

            if ($gappedPrerequisite) {
                $row['relation'] = 'symptom';
                $row['related_skill_id'] = $gappedPrerequisite->id;

                return $row;
            }

            $gappedDependent = $skills->first(
                fn ($other) => $other->id !== $skill->id
                    && $gappedSkillIds->contains($other->id)
                    && $other->prerequisites->contains('id', $skill->id)
            );

            if ($gappedDependent) {
                $row['relation'] = 'root_cause';
                $row['related_skill_id'] = $gappedDependent->id;
            }

            return $row;
        });
    }
}
