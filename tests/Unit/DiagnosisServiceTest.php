<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\DiagnosisService;
use PHPUnit\Framework\TestCase;

class DiagnosisServiceTest extends TestCase
{
    public function test_a_correct_answer_has_no_conceptual_gap_and_is_marked_mastered(): void
    {
        $question = new Question([
            'correct_option' => 'A',
            'kssm_code' => '9.2.2',
            'distractor_map' => ['B' => 'metal_inherently_colder'],
        ]);

        $result = (new DiagnosisService)->diagnose($question, 'A');

        $this->assertTrue($result['correct']);
        $this->assertNull($result['conceptual']['tag']);
        $this->assertFalse($result['conceptual']['gap']);
        $this->assertSame('9.2.2', $result['curriculum']['kssm_code']);
        $this->assertTrue($result['curriculum']['mastered']);
    }

    public function test_a_wrong_answer_is_tagged_with_its_mapped_misconception(): void
    {
        $question = new Question([
            'correct_option' => 'A',
            'kssm_code' => '9.2.2',
            'distractor_map' => ['B' => 'metal_inherently_colder'],
        ]);

        $result = (new DiagnosisService)->diagnose($question, 'B');

        $this->assertFalse($result['correct']);
        $this->assertTrue($result['conceptual']['gap']);
        $this->assertSame('metal_inherently_colder', $result['conceptual']['tag']);
        $this->assertSame('9.2.2', $result['curriculum']['kssm_code']);
        $this->assertFalse($result['curriculum']['mastered']);
    }
}
