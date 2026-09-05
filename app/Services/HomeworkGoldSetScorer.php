<?php

namespace App\Services;

/**
 * Scores one HomeworkDiagnosisService diagnosis against one hand-authored
 * gold-set entry, dimension by dimension. Pure - no I/O, no live model call -
 * so `php artisan homework:evaluate` can aggregate these across a gold-set
 * without the scoring logic itself depending on a live Gemini response.
 */
class HomeworkGoldSetScorer
{
    /**
     * @param  array{correct: bool, conceptual_tag: ?string, method_issue: ?string, reasoning_issue: ?string, expected_needs_review?: bool}  $gold
     * @param  array<string, mixed>  $diagnosis
     * @return array{correct_match: bool, conceptual_match: bool, method_match: bool, reasoning_match: bool, needs_review_match: bool}
     */
    public function score(array $gold, array $diagnosis): array
    {
        return [
            'correct_match' => $gold['correct'] === $diagnosis['correct'],
            'conceptual_match' => $gold['conceptual_tag'] === ($diagnosis['conceptual']['tag'] ?? null),
            'method_match' => $gold['method_issue'] === ($diagnosis['method']['issue'] ?? null),
            'reasoning_match' => $gold['reasoning_issue'] === ($diagnosis['reasoning']['issue'] ?? null),
            'needs_review_match' => ($gold['expected_needs_review'] ?? false) === ($diagnosis['needs_review'] ?? false),
        ];
    }
}
