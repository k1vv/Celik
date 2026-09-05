<?php

namespace Database\Seeders;

use App\Models\Skill;
use App\Models\Subject;
use App\Models\TopicGrounding;
use Illuminate\Database\Seeder;

/**
 * Real syllabus grounding for the two authored demo topics, sourced from the
 * MOE/Sasbadi Form 4 Physics KSSM textbook, Theme 3 "Heat" - not a fabricated
 * placeholder sentence. Codes are the textbook's own printed section numbers
 * (verified against its table of contents), not invented DSKP standard codes.
 *
 * Also seeds the one skill_prerequisites edge this project has real content
 * for (see engine.md §8): you can't reason about how quickly an object's
 * temperature changes (Specific Heat Capacity) without first understanding
 * that heat flows hot -> cold until both reach the same temperature
 * (Thermal Equilibrium).
 */
class TopicGroundingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $physics = Subject::firstOrCreate(['name' => 'Physics']);

        $thermalEquilibrium = Skill::firstOrCreate([
            'subject_id' => $physics->id,
            'name' => 'Thermal Equilibrium',
        ]);

        $specificHeatCapacity = Skill::firstOrCreate([
            'subject_id' => $physics->id,
            'name' => 'Specific Heat Capacity',
        ]);

        $specificHeatCapacity->prerequisites()->syncWithoutDetaching($thermalEquilibrium);

        TopicGrounding::updateOrCreate(
            ['kssm_code' => '4.1'],
            [
                'syllabus_excerpt' => 'When two objects are in thermal contact, the temperature of '
                    .'the hot object will drop while the temperature of the cold object will rise '
                    .'until the temperature of both objects become the same. Net heat transfer '
                    .'between the two objects becomes zero. Both objects are said to be in thermal '
                    .'equilibrium.',
                'rubric' => 'Full marks require explaining that heat flows from the hotter object to '
                    .'the cooler object until both reach the same temperature (thermal equilibrium) - '
                    .'not that either object is inherently hot or cold.',
                'tag_vocabulary' => [
                    'conceptual' => ['metal_inherently_colder', 'confuses_heat_and_temperature'],
                    'method' => ['no_direction_of_heat_flow_stated'],
                    'reasoning' => ['reversed_cause_and_effect'],
                ],
            ],
        );

        TopicGrounding::updateOrCreate(
            ['kssm_code' => '4.2'],
            [
                'syllabus_excerpt' => 'Specific heat capacity, c, of a substance is the quantity of '
                    .'heat required to raise the temperature of 1 kg of the substance by 1°C, '
                    .'c = Q / (mΔθ). Water has a high specific heat capacity, which makes it a '
                    .'good cooling agent.',
                'rubric' => 'Full marks require correctly applying c = Q / (mΔθ) with correct units '
                    .'(J kg⁻¹ °C⁻¹), and recognising specific heat capacity as a per-unit-mass '
                    .'property, distinct from heat capacity.',
                'tag_vocabulary' => [
                    'conceptual' => ['confuses_heat_capacity_and_specific_heat_capacity'],
                    'method' => ['missing_units', 'wrong_formula_variables'],
                    'reasoning' => ['ignores_mass_dependence'],
                ],
            ],
        );
    }
}
