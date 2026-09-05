<?php

namespace Tests\Feature;

use App\Models\PracticeSet;
use Database\Seeders\AssignmentSeeder;
use Database\Seeders\HomeworkQuestionSeeder;
use Database\Seeders\PhysicsQuizQuestionSeeder;
use Database\Seeders\PracticeSetSeeder;
use Database\Seeders\TopicGroundingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeSetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_real_thermal_equilibrium_practice_set_for_the_physics_classroom(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);
        $this->seed(PhysicsQuizQuestionSeeder::class);
        $this->seed(AssignmentSeeder::class);
        $this->seed(PracticeSetSeeder::class);

        $set = PracticeSet::where('title', 'Thermal Equilibrium Practice')->first();

        $this->assertNotNull($set);
        $this->assertSame('Form 4 Physics', $set->classroom->name);
        $this->assertSame('Thermal Equilibrium', $set->skill->name);
        $this->assertGreaterThanOrEqual(2, $set->questions()->count());
    }
}
