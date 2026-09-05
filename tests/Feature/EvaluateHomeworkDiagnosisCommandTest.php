<?php

namespace Tests\Feature;

use App\Services\HomeworkDiagnosisService;
use Database\Seeders\HomeworkQuestionSeeder;
use Database\Seeders\TopicGroundingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EvaluateHomeworkDiagnosisCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_accuracy_and_consistency_against_the_seeded_gold_set(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);

        $diagnosisService = Mockery::mock(HomeworkDiagnosisService::class);
        $diagnosisService->shouldReceive('diagnose')->andReturn([
            'correct' => true,
            'conceptual' => ['gap' => false, 'tag' => null],
            'method' => ['issue' => null],
            'reasoning' => ['issue' => null],
            'confidence' => 0.9,
            'needs_review' => false,
        ]);
        $this->app->instance(HomeworkDiagnosisService::class, $diagnosisService);

        $this->artisan('homework:evaluate', ['--repeats' => 1])
            ->assertExitCode(0)
            ->expectsOutputToContain('Accuracy')
            ->expectsOutputToContain('Cross-call consistency');
    }

    public function test_it_fails_clearly_when_no_gold_set_question_is_seeded(): void
    {
        $this->artisan('homework:evaluate', ['--repeats' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('No gold-set entries could be resolved');
    }
}
