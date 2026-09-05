<?php

namespace App\Services;

use App\Models\Question;

/**
 * Orchestrates `php artisan homework:evaluate`: runs a gold-set through the
 * real HomeworkDiagnosisService and aggregates the result. `HomeworkGoldSetScorer`
 * owns per-call scoring; this class owns repetition and aggregation.
 *
 * Each gold entry is diagnosed `$repeats` times (not once) - the PNG vs PDF
 * finding in PROGRESS.md showed Gemini can disagree with itself across
 * separate submissions of equivalent content, which the service's own
 * internal self-consistency double-call (within one submission) can't catch.
 * `consistency_rate` here measures agreement *across* those repeated calls.
 */
class HomeworkEvaluationRunner
{
    public function __construct(private HomeworkDiagnosisService $diagnosisService) {}

    /**
     * @param  list<array{question: Question, answer: string, gold: array<string, mixed>, label?: string}>  $entries
     * @return array{entries: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function run(array $entries, int $repeats = 3): array
    {
        $scorer = new HomeworkGoldSetScorer;
        $entryReports = [];

        foreach ($entries as $entry) {
            $diagnoses = [];

            for ($i = 0; $i < $repeats; $i++) {
                $diagnoses[] = $this->diagnosisService->diagnose($entry['question'], $entry['answer']);
            }

            $scores = array_map(fn (array $diagnosis) => $scorer->score($entry['gold'], $diagnosis), $diagnoses);

            $entryReports[] = [
                'label' => $entry['label'] ?? $entry['question']->kssm_code,
                'diagnoses' => $diagnoses,
                'scores' => $scores,
                'consistent' => $this->allAgree($diagnoses),
                'confidences' => array_map(fn (array $d, array $s) => [
                    'confidence' => (float) $d['confidence'],
                    'matched' => $s['correct_match'],
                ], $diagnoses, $scores),
            ];
        }

        return [
            'entries' => $entryReports,
            'summary' => $this->summarize($entryReports),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $diagnoses
     */
    private function allAgree(array $diagnoses): bool
    {
        $signatures = array_map(fn (array $d) => [
            $d['correct'],
            $d['conceptual']['tag'] ?? null,
            $d['method']['issue'] ?? null,
            $d['reasoning']['issue'] ?? null,
        ], $diagnoses);

        return count(array_unique(array_map(fn ($s) => serialize($s), $signatures))) === 1;
    }

    /**
     * @param  list<array<string, mixed>>  $entryReports
     * @return array<string, mixed>
     */
    private function summarize(array $entryReports): array
    {
        $allScores = array_merge(...array_map(fn ($e) => $e['scores'], $entryReports));
        $allConfidences = array_merge(...array_map(fn ($e) => $e['confidences'], $entryReports));

        $matched = array_filter($allConfidences, fn (array $c) => $c['matched']);
        $mismatched = array_filter($allConfidences, fn (array $c) => ! $c['matched']);

        return [
            'accuracy' => $this->rateOf($allScores, 'correct_match'),
            'conceptual_tag_match' => $this->rateOf($allScores, 'conceptual_match'),
            'method_match' => $this->rateOf($allScores, 'method_match'),
            'reasoning_match' => $this->rateOf($allScores, 'reasoning_match'),
            'needs_review_match' => $this->rateOf($allScores, 'needs_review_match'),
            'consistency_rate' => $this->rateOf($entryReports, 'consistent'),
            'mean_confidence_when_matched' => $this->meanConfidence($matched),
            'mean_confidence_when_mismatched' => $this->meanConfidence($mismatched),
        ];
    }

    /**
     * @param  array<int, array{confidence: float, matched: bool}>  $confidences
     */
    private function meanConfidence(array $confidences): ?float
    {
        if ($confidences === []) {
            return null;
        }

        return array_sum(array_column($confidences, 'confidence')) / count($confidences);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rateOf(array $rows, string $key): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $matches = array_filter($rows, fn (array $row) => $row[$key]);

        return count($matches) / count($rows);
    }
}
