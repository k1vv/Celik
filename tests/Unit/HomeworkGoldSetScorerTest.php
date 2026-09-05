<?php

namespace Tests\Unit;

use App\Services\HomeworkGoldSetScorer;
use PHPUnit\Framework\TestCase;

class HomeworkGoldSetScorerTest extends TestCase
{
    public function test_a_diagnosis_matching_a_fully_correct_gold_entry_matches_on_every_dimension(): void
    {
        $gold = [
            'correct' => true,
            'conceptual_tag' => null,
            'method_issue' => null,
            'reasoning_issue' => null,
        ];

        $diagnosis = [
            'correct' => true,
            'conceptual' => ['gap' => false, 'tag' => null],
            'method' => ['issue' => null],
            'reasoning' => ['issue' => null],
            'confidence' => 0.95,
        ];

        $result = (new HomeworkGoldSetScorer)->score($gold, $diagnosis);

        $this->assertTrue($result['correct_match']);
        $this->assertTrue($result['conceptual_match']);
        $this->assertTrue($result['method_match']);
        $this->assertTrue($result['reasoning_match']);
    }

    public function test_a_diagnosis_that_stays_trusted_when_gold_expects_review_fails_the_review_match(): void
    {
        $gold = [
            'correct' => true,
            'conceptual_tag' => null,
            'method_issue' => null,
            'reasoning_issue' => null,
            'expected_needs_review' => true,
        ];

        $diagnosis = [
            'correct' => true,
            'conceptual' => ['gap' => false, 'tag' => null],
            'method' => ['issue' => null],
            'reasoning' => ['issue' => null],
            'confidence' => 0.95,
            'needs_review' => false,
        ];

        $result = (new HomeworkGoldSetScorer)->score($gold, $diagnosis);

        $this->assertFalse($result['needs_review_match']);
    }
}
