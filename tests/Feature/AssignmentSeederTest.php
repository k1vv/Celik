<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Skill;
use Database\Seeders\AssignmentSeeder;
use Database\Seeders\HomeworkQuestionSeeder;
use Database\Seeders\TopicGroundingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_physics_classroom_with_its_own_dedicated_student(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);
        $this->seed(AssignmentSeeder::class);

        $classroom = Classroom::where('name', 'Form 4 Physics')->firstOrFail();

        $this->assertSame('Physics', $classroom->subject->name);
        $this->assertTrue($classroom->students->contains(fn ($student) => $student->email === 'physics.student1@example.com'));
        // The Physics classroom must not reuse LearningDataSeeder's Math cohort.
        $this->assertFalse($classroom->students->contains(fn ($student) => $student->email === 'student1@example.com'));
    }

    public function test_it_seeds_a_heat_module_containing_both_seeded_skills(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);
        $this->seed(AssignmentSeeder::class);

        $thermalEquilibrium = Skill::where('name', 'Thermal Equilibrium')->firstOrFail();
        $specificHeatCapacity = Skill::where('name', 'Specific Heat Capacity')->firstOrFail();

        $this->assertNotNull($thermalEquilibrium->module);
        $this->assertSame('Heat', $thermalEquilibrium->module->name);
        $this->assertSame($thermalEquilibrium->module_id, $specificHeatCapacity->module_id);
    }

    public function test_it_seeds_homework_1_assigned_to_the_real_thermal_equilibrium_question(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);
        $this->seed(AssignmentSeeder::class);

        $classroom = Classroom::where('name', 'Form 4 Physics')->firstOrFail();
        $assignment = Assignment::where('title', 'Homework 1')->firstOrFail();

        $this->assertSame($classroom->id, $assignment->classroom_id);
        $this->assertSame('4.1', $assignment->questions->first()->kssm_code);
        $this->assertNotNull($assignment->due_at);
    }
}
