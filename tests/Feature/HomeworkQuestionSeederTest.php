<?php

namespace Tests\Feature;

use App\Models\Question;
use Database\Seeders\HomeworkQuestionSeeder;
use Database\Seeders\TopicGroundingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeworkQuestionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_real_homework_question_for_the_seeded_thermal_equilibrium_topic(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);

        $question = Question::where('kssm_code', '4.1')->where('type', 'homework')->first();

        $this->assertNotNull($question);
        $this->assertSame('Thermal Equilibrium', $question->skill->name);
        $this->assertStringContainsString('metal spoon', $question->body);
    }

    public function test_it_seeds_a_real_homework_question_for_the_seeded_specific_heat_capacity_topic(): void
    {
        $this->seed(TopicGroundingSeeder::class);
        $this->seed(HomeworkQuestionSeeder::class);

        $question = Question::where('kssm_code', '4.2')->where('type', 'homework')->first();

        $this->assertNotNull($question);
        $this->assertSame('Specific Heat Capacity', $question->skill->name);
        $this->assertStringContainsString('specific heat capacity', $question->body);
    }
}
