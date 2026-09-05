<?php

namespace Tests\Unit;

use Database\Seeders\GoldSets\HeatHomeworkGoldSet;
use PHPUnit\Framework\TestCase;

class HeatHomeworkGoldSetTest extends TestCase
{
    public function test_every_entry_has_a_seeded_kssm_code_and_a_complete_gold_label(): void
    {
        $entries = HeatHomeworkGoldSet::entries();

        $this->assertNotEmpty($entries);

        foreach ($entries as $entry) {
            $this->assertContains($entry['kssm_code'], ['4.1', '4.2']);
            $this->assertNotEmpty($entry['answer']);
            $this->assertArrayHasKey('correct', $entry['gold']);
            $this->assertArrayHasKey('conceptual_tag', $entry['gold']);
            $this->assertArrayHasKey('method_issue', $entry['gold']);
            $this->assertArrayHasKey('reasoning_issue', $entry['gold']);
            $this->assertArrayHasKey('expected_needs_review', $entry['gold']);
        }
    }

    public function test_each_seeded_topic_has_at_least_four_entries(): void
    {
        $entries = HeatHomeworkGoldSet::entries();

        $countByTopic = array_count_values(array_column($entries, 'kssm_code'));

        $this->assertGreaterThanOrEqual(4, $countByTopic['4.1'] ?? 0);
        $this->assertGreaterThanOrEqual(4, $countByTopic['4.2'] ?? 0);
    }
}
