<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\HomeworkDiagnosisService;
use App\Services\HomeworkEvaluationRunner;
use Mockery;
use Tests\TestCase;

class HomeworkEvaluationRunnerTest extends TestCase
{
    public function test_a_single_matching_entry_scores_full_accuracy_and_full_consistency(): void
    {
        $question = new Question(['kssm_code' => '4.1']);

        $diagnosisService = Mockery::mock(HomeworkDiagnosisService::class);
        $diagnosisService->shouldReceive('diagnose')->once()->andReturn([
            'correct' => true,
            'conceptual' => ['gap' => false, 'tag' => null],
            'method' => ['issue' => null],
            'reasoning' => ['issue' => null],
            'confidence' => 0.95,
            'needs_review' => false,
        ]);

        $entries = [[
            'question' => $question,
            'answer' => 'Some correct answer.',
            'gold' => [
                'correct' => true,
                'conceptual_tag' => null,
                'method_issue' => null,
                'reasoning_issue' => null,
                'expected_needs_review' => false,
            ],
        ]];

        $report = (new HomeworkEvaluationRunner($diagnosisService))->run($entries, repeats: 1);

        $this->assertSame(1.0, $report['summary']['accuracy']);
        $this->assertSame(1.0, $report['summary']['consistency_rate']);
    }

    public function test_repeated_calls_that_disagree_on_the_same_input_are_flagged_inconsistent(): void
    {
        $question = new Question(['kssm_code' => '4.1']);

        $diagnosisService = Mockery::mock(HomeworkDiagnosisService::class);
        $diagnosisService->shouldReceive('diagnose')
            ->twice()
            ->andReturn(
                ['correct' => true, 'conceptual' => ['gap' => false, 'tag' => null], 'method' => ['issue' => null], 'reasoning' => ['issue' => null], 'confidence' => 0.9, 'needs_review' => false],
                ['correct' => false, 'conceptual' => ['gap' => true, 'tag' => 'metal_inherently_colder'], 'method' => ['issue' => null], 'reasoning' => ['issue' => null], 'confidence' => 0.9, 'needs_review' => false],
            );

        $entries = [[
            'question' => $question,
            'answer' => 'Same content, submitted twice.',
            'gold' => [
                'correct' => true,
                'conceptual_tag' => null,
                'method_issue' => null,
                'reasoning_issue' => null,
                'expected_needs_review' => false,
            ],
        ]];

        $report = (new HomeworkEvaluationRunner($diagnosisService))->run($entries, repeats: 2);

        $this->assertSame(0.0, $report['summary']['consistency_rate']);
        $this->assertFalse($report['entries'][0]['consistent']);
    }

    public function test_confidence_is_averaged_separately_for_correct_and_incorrect_matches(): void
    {
        $question = new Question(['kssm_code' => '4.1']);

        $diagnosisService = Mockery::mock(HomeworkDiagnosisService::class);
        $diagnosisService->shouldReceive('diagnose')
            ->twice()
            ->andReturn(
                ['correct' => true, 'conceptual' => ['gap' => false, 'tag' => null], 'method' => ['issue' => null], 'reasoning' => ['issue' => null], 'confidence' => 0.9, 'needs_review' => false],
                ['correct' => false, 'conceptual' => ['gap' => false, 'tag' => null], 'method' => ['issue' => null], 'reasoning' => ['issue' => null], 'confidence' => 0.4, 'needs_review' => true],
            );

        $entries = [[
            'question' => $question,
            'answer' => 'A correct answer.',
            'gold' => [
                'correct' => true,
                'conceptual_tag' => null,
                'method_issue' => null,
                'reasoning_issue' => null,
                'expected_needs_review' => false,
            ],
        ]];

        $report = (new HomeworkEvaluationRunner($diagnosisService))->run($entries, repeats: 2);

        $this->assertSame(0.9, $report['summary']['mean_confidence_when_matched']);
        $this->assertSame(0.4, $report['summary']['mean_confidence_when_mismatched']);
    }
}
