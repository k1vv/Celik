<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Services\HomeworkEvaluationRunner;
use Database\Seeders\GoldSets\HeatHomeworkGoldSet;
use Illuminate\Console\Command;

/**
 * Runs the hand-authored Heat gold-set (HeatHomeworkGoldSet) through the
 * real, live HomeworkDiagnosisService and reports accuracy, per-dimension
 * tag-match, confidence calibration, and cross-call consistency - see
 * PROGRESS.md's "no calibration harness" gap and the PNG-vs-PDF
 * inconsistency finding this exists to quantify.
 *
 * Deliberately not exercised in the PHPUnit suite against a real API key -
 * `HomeworkDiagnosisService` itself is already covered against Http::fake(),
 * this command's own wiring is covered the same way (a faked service
 * binding), and a real run is a manual `sail artisan homework:evaluate`
 * against the live Gemini key.
 */
class EvaluateHomeworkDiagnosis extends Command
{
    protected $signature = 'homework:evaluate {--repeats=3 : How many times to diagnose each gold-set answer, to measure consistency across separate submissions of the same content}';

    protected $description = 'Score the Heat gold-set against the live HomeworkDiagnosisService and report accuracy/tag-match/confidence-calibration/consistency.';

    public function handle(HomeworkEvaluationRunner $runner): int
    {
        $entries = $this->resolveEntries();

        if ($entries === []) {
            $this->error('No gold-set entries could be resolved to a seeded homework Question. Run `php artisan db:seed` first.');

            return self::FAILURE;
        }

        $repeats = max(1, (int) $this->option('repeats'));
        $report = $runner->run($entries, $repeats);

        $this->renderTable($report);
        $this->renderSummary($report['summary'], $repeats);

        return self::SUCCESS;
    }

    /**
     * @return list<array{question: Question, answer: string, gold: array<string, mixed>, label: string}>
     */
    private function resolveEntries(): array
    {
        $entries = [];

        foreach (HeatHomeworkGoldSet::entries() as $entry) {
            $question = Question::where('kssm_code', $entry['kssm_code'])
                ->where('type', 'homework')
                ->first();

            if (! $question) {
                $this->warn("No seeded homework question for kssm_code {$entry['kssm_code']} - skipping \"{$entry['label']}\".");

                continue;
            }

            $entries[] = [
                'question' => $question,
                'answer' => $entry['answer'],
                'gold' => $entry['gold'],
                'label' => "{$entry['kssm_code']}: {$entry['label']}",
            ];
        }

        return $entries;
    }

    /**
     * @param  array{entries: list<array<string, mixed>>, summary: array<string, mixed>}  $report
     */
    private function renderTable(array $report): void
    {
        $rows = array_map(fn (array $entry) => [
            $entry['label'],
            $this->fraction($entry['scores'], 'correct_match'),
            $this->fraction($entry['scores'], 'conceptual_match'),
            $this->fraction($entry['scores'], 'method_match'),
            $this->fraction($entry['scores'], 'reasoning_match'),
            $this->fraction($entry['scores'], 'needs_review_match'),
            $entry['consistent'] ? 'yes' : 'NO',
        ], $report['entries']);

        $this->table(['Entry', 'Correct', 'Conceptual', 'Method', 'Reasoning', 'Review', 'Consistent'], $rows);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderSummary(array $summary, int $repeats): void
    {
        $this->newLine();
        $this->line('Accuracy: '.$this->percent($summary['accuracy']));
        $this->line('Conceptual tag match: '.$this->percent($summary['conceptual_tag_match']));
        $this->line('Method tag match: '.$this->percent($summary['method_match']));
        $this->line('Reasoning tag match: '.$this->percent($summary['reasoning_match']));
        $this->line('Needs-review match: '.$this->percent($summary['needs_review_match']));
        $this->line("Cross-call consistency (repeats={$repeats}): ".$this->percent($summary['consistency_rate']));
        $this->line('Mean confidence when matched: '.$this->confidence($summary['mean_confidence_when_matched']));
        $this->line('Mean confidence when mismatched: '.$this->confidence($summary['mean_confidence_when_mismatched']));
    }

    /**
     * @param  list<array<string, mixed>>  $scores
     */
    private function fraction(array $scores, string $key): string
    {
        $matches = count(array_filter($scores, fn (array $score) => $score[$key]));

        return "{$matches}/".count($scores);
    }

    private function percent(float $rate): string
    {
        return number_format($rate * 100, 1).'%';
    }

    private function confidence(?float $confidence): string
    {
        return $confidence === null ? 'n/a' : number_format($confidence, 2);
    }
}
