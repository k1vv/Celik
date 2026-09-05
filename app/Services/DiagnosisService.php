<?php

namespace App\Services;

use App\Models\Question;

/**
 * Turns a student's chosen option into a structured diagnosis, per the
 * Conceptual + Curriculum dimensions described in engine.md. For a
 * multiple-choice item this is a lookup, not an AI call: the misconception
 * each wrong option reveals is decided once, when the item is authored
 * (Question::$distractor_map), not re-derived per student.
 */
class DiagnosisService
{
    /**
     * @return array{
     *     correct: bool,
     *     conceptual: array{gap: bool, tag: ?string},
     *     curriculum: array{kssm_code: ?string, mastered: bool},
     * }
     */
    public function diagnose(Question $question, string $selectedOption): array
    {
        $correct = $question->correct_option === $selectedOption;

        return [
            'correct' => $correct,
            'conceptual' => [
                'gap' => ! $correct,
                'tag' => $correct ? null : ($question->distractor_map[$selectedOption] ?? null),
            ],
            'curriculum' => [
                'kssm_code' => $question->kssm_code,
                'mastered' => $correct,
            ],
        ];
    }
}
