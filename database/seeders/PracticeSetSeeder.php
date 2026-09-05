<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\PracticeSet;
use App\Models\Question;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * Bundles PhysicsQuizQuestionSeeder's real MCQ items into real teacher-
 * authored PracticeSets released to the Physics classroom AssignmentSeeder
 * creates - the demo content behind the frontend's previously-hardcoded
 * `practiceSets` array (see architecture.md's PracticeSet section).
 *
 * Depends on PhysicsQuizQuestionSeeder and AssignmentSeeder having already
 * run (needs both the questions and the classroom to exist); idempotent via
 * firstOrCreate/syncWithoutDetaching like its siblings.
 */
class PracticeSetSeeder extends Seeder
{
    public function run(): void
    {
        $classroom = Classroom::where('name', 'Form 4 Physics')->firstOrFail();

        $thermalEquilibrium = Skill::where('name', 'Thermal Equilibrium')->firstOrFail();
        $thermalSet = PracticeSet::firstOrCreate(
            ['classroom_id' => $classroom->id, 'title' => 'Thermal Equilibrium Practice'],
            [
                'skill_id' => $thermalEquilibrium->id,
                'description' => 'Strengthen your understanding of how heat flows between objects at different temperatures.',
                'difficulty' => 'Foundation',
            ],
        );
        $thermalQuestions = Question::where('kssm_code', '4.1')->where('type', 'mcq')->orderBy('id')->get();
        $thermalSet->questions()->syncWithoutDetaching(
            $thermalQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['order' => $index]])
        );

        $specificHeatCapacity = Skill::where('name', 'Specific Heat Capacity')->firstOrFail();
        $capacitySet = PracticeSet::firstOrCreate(
            ['classroom_id' => $classroom->id, 'title' => 'Specific Heat Capacity Practice'],
            [
                'skill_id' => $specificHeatCapacity->id,
                'description' => 'Practice applying specific heat capacity to compare how different materials heat up.',
                'difficulty' => 'Core',
            ],
        );
        $capacityQuestions = Question::where('kssm_code', '4.2')->where('type', 'mcq')->orderBy('id')->get();
        $capacitySet->questions()->syncWithoutDetaching(
            $capacityQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['order' => $index]])
        );
    }
}
