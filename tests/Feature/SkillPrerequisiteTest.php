<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillPrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_skill_can_have_a_prerequisite_and_the_relationship_is_bidirectional(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $thermalEquilibrium = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $specificHeatCapacity = Skill::create(['subject_id' => $subject->id, 'name' => 'Specific Heat Capacity']);

        $specificHeatCapacity->prerequisites()->attach($thermalEquilibrium);

        $this->assertTrue($specificHeatCapacity->prerequisites->contains($thermalEquilibrium));
        $this->assertTrue($thermalEquilibrium->dependents->contains($specificHeatCapacity));
    }
}
