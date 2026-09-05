<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use App\Services\GapSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GapSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function gapAttempt(User $student, Skill $skill, string $tag): void
    {
        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Question body',
        ]);

        $attempt = Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'is_correct' => false,
        ]);

        $attempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => $tag]);
    }

    public function test_a_gap_on_a_prerequisite_skill_is_reported_as_the_root_cause_of_a_gap_on_its_dependent(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $thermalEquilibrium = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $specificHeatCapacity = Skill::create(['subject_id' => $subject->id, 'name' => 'Specific Heat Capacity']);
        $specificHeatCapacity->prerequisites()->attach($thermalEquilibrium);

        $students = User::factory()->count(2)->create(['role' => 'student']);

        // Both students are gapped on the downstream skill.
        foreach ($students as $student) {
            $this->gapAttempt($student, $specificHeatCapacity, 'confuses_heat_capacity_and_specific_heat_capacity');
        }

        // Only one is also gapped on the prerequisite.
        $this->gapAttempt($students->first(), $thermalEquilibrium, 'metal_inherently_colder');

        $summary = (new GapSummaryService)->forStudents($students->pluck('id'));

        $downstreamRow = $summary->firstWhere('tag', 'confuses_heat_capacity_and_specific_heat_capacity');
        $upstreamRow = $summary->firstWhere('tag', 'metal_inherently_colder');

        $this->assertSame('symptom', $downstreamRow['relation']);
        $this->assertSame($thermalEquilibrium->id, $downstreamRow['related_skill_id']);

        $this->assertSame('root_cause', $upstreamRow['relation']);
        $this->assertSame($specificHeatCapacity->id, $upstreamRow['related_skill_id']);
    }

    public function test_gaps_on_unrelated_skills_are_not_annotated(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skillA = Skill::create(['subject_id' => $subject->id, 'name' => 'Skill A']);
        $skillB = Skill::create(['subject_id' => $subject->id, 'name' => 'Skill B']);

        $student = User::factory()->create(['role' => 'student']);
        $this->gapAttempt($student, $skillA, 'tag_a');
        $this->gapAttempt($student, $skillB, 'tag_b');

        $summary = (new GapSummaryService)->forStudents(collect([$student->id]));

        $this->assertNull($summary->firstWhere('tag', 'tag_a')['relation']);
        $this->assertNull($summary->firstWhere('tag', 'tag_b')['relation']);
    }
}
