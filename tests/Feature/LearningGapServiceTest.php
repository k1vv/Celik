<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use App\Services\LearningGapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningGapServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tag_missing_from_a_students_most_recent_attempt_on_that_skill_counts_as_cleared(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $student = User::factory()->create(['role' => 'student']);
        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q']);

        $first = Attempt::create(['user_id' => $student->id, 'question_id' => $question->id, 'skill_id' => $skill->id, 'source' => 'homework', 'is_correct' => false, 'created_at' => now()->subDay()]);
        $first->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder']);

        // A later attempt on the same skill no longer shows the tag.
        Attempt::create(['user_id' => $student->id, 'question_id' => $question->id, 'skill_id' => $skill->id, 'source' => 'homework', 'is_correct' => true, 'created_at' => now()]);

        $result = (new LearningGapService)->forStudent($student);

        $this->assertSame(1, $result['stats']['gapsCleared']);
        $this->assertSame(1, $result['stats']['totalGaps']);
        $this->assertCount(0, $result['openGaps']);
        $this->assertCount(1, $result['clearedGaps']);
        $this->assertSame('metal_inherently_colder', $result['clearedGaps'][0]['tag']);
        $this->assertTrue($result['clearedGaps'][0]['cleared']);
    }

    public function test_a_tag_still_present_in_the_most_recent_attempt_stays_open(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $student = User::factory()->create(['role' => 'student']);
        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q']);

        $attempt = Attempt::create(['user_id' => $student->id, 'question_id' => $question->id, 'skill_id' => $skill->id, 'source' => 'homework', 'is_correct' => false]);
        $attempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder']);

        $result = (new LearningGapService)->forStudent($student);

        $this->assertSame(0, $result['stats']['gapsCleared']);
        $this->assertSame(1, $result['stats']['totalGaps']);
        $this->assertCount(1, $result['openGaps']);
        $this->assertSame('conceptual', $result['openGaps'][0]['dimension']);
        $this->assertSame('metal_inherently_colder', $result['openGaps'][0]['tag']);
        $this->assertFalse($result['openGaps'][0]['cleared']);
    }
}
