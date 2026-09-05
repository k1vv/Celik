<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\TopicGrounding;
use Database\Seeders\TopicGroundingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicsGroundingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_real_textbook_grounded_content_for_the_two_demo_topics(): void
    {
        $this->seed(TopicGroundingSeeder::class);

        $thermalEquilibrium = TopicGrounding::forKssmCode('4.1');
        $specificHeatCapacity = TopicGrounding::forKssmCode('4.2');

        $this->assertNotNull($thermalEquilibrium);
        $this->assertNotNull($specificHeatCapacity);

        foreach ([$thermalEquilibrium, $specificHeatCapacity] as $grounding) {
            $this->assertNotEmpty($grounding->tag_vocabulary['conceptual'] ?? []);
            $this->assertNotEmpty($grounding->tag_vocabulary['method'] ?? []);
            $this->assertNotEmpty($grounding->tag_vocabulary['reasoning'] ?? []);
        }
    }

    public function test_it_seeds_the_prerequisite_edge_between_the_two_demo_skills(): void
    {
        $this->seed(TopicGroundingSeeder::class);

        $specificHeatCapacity = Skill::where('name', 'Specific Heat Capacity')->firstOrFail();
        $thermalEquilibrium = Skill::where('name', 'Thermal Equilibrium')->firstOrFail();

        $this->assertTrue($specificHeatCapacity->prerequisites->contains($thermalEquilibrium));
    }
}
