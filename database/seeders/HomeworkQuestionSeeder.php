<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * Real homework items for the Heat-chapter topics grounded in
 * TopicGroundingSeeder's content - the item bank half of the "real
 * grounding exists, item bank does not" gap tracked in PROGRESS.md. Sourced
 * from real Form 4 Physics worksheet questions (not invented), so a live
 * submission through SubmitHomework.jsx exercises HomeworkDiagnosisService
 * against content the seeded tag_vocabulary/rubric actually covers.
 */
class HomeworkQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $thermalEquilibrium = Skill::where('name', 'Thermal Equilibrium')->firstOrFail();
        $specificHeatCapacity = Skill::where('name', 'Specific Heat Capacity')->firstOrFail();

        Question::firstOrCreate(
            ['kssm_code' => '4.1', 'type' => 'homework'],
            [
                'skill_id' => $thermalEquilibrium->id,
                'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            ],
        );

        Question::firstOrCreate(
            ['kssm_code' => '4.2', 'type' => 'homework'],
            [
                'skill_id' => $specificHeatCapacity->id,
                'body' => 'A 2 kg block of metal absorbs 9000 J of heat and its temperature rises from '
                    .'25°C to 35°C. Calculate the specific heat capacity of the metal, showing your '
                    .'working and units.',
            ],
        );
    }
}
