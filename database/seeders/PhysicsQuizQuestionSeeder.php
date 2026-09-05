<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * Real quiz (MCQ) items for the Heat-chapter topics, distractors mapped to
 * the same real tag_vocabulary TopicGroundingSeeder authored for the
 * homework path (engine.md §6: one shared vocabulary across both paths).
 * Fills part of the "real grounding exists, item bank does not" gap for the
 * quiz side specifically - previously only Math had any quiz-type Question.
 *
 * `options` is a plain array of full option text (matching
 * LearningDataSeeder's convention); `correct_option`/`distractor_map` are
 * keyed by that exact text, not a letter - DiagnosisService compares by
 * value against the option the student actually picked.
 */
class PhysicsQuizQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $thermalEquilibrium = Skill::where('name', 'Thermal Equilibrium')->firstOrFail();
        $specificHeatCapacity = Skill::where('name', 'Specific Heat Capacity')->firstOrFail();

        $conductsHeatFaster = 'Metal conducts heat away from your hand faster than wood does, even though both are at the same temperature';
        $metalNaturallyColder = 'Metal is naturally a colder material than wood';
        $metalLowerTemperature = 'The metal spoon has a lower temperature than the wooden spoon';

        Question::firstOrCreate(
            ['kssm_code' => '4.1', 'type' => 'mcq', 'body' => 'A metal spoon and a wooden spoon have both been resting on a kitchen table overnight, in the same room. Why does the metal spoon feel colder when you touch it?'],
            [
                'skill_id' => $thermalEquilibrium->id,
                'options' => [$conductsHeatFaster, $metalNaturallyColder, $metalLowerTemperature],
                'correct_option' => $conductsHeatFaster,
                'distractor_map' => [$metalNaturallyColder => 'metal_inherently_colder', $metalLowerTemperature => 'confuses_heat_and_temperature'],
                'difficulty' => 2,
            ],
        );

        $moveTowardCommonTemperature = 'They both move toward a common temperature between 20°C and 80°C, then stop changing';
        $hotBlockHoldsHeat = 'The hot block stays at 80°C because metal holds onto its own heat';
        $onlyFeelMatters = 'Only which block feels hotter to touch matters, not their actual temperatures';

        Question::firstOrCreate(
            ['kssm_code' => '4.1', 'type' => 'mcq', 'body' => 'Two identical metal blocks, one at 80°C and one at 20°C, are pressed together and left for ten minutes. What happens to their temperatures?'],
            [
                'skill_id' => $thermalEquilibrium->id,
                'options' => [$moveTowardCommonTemperature, $hotBlockHoldsHeat, $onlyFeelMatters],
                'correct_option' => $moveTowardCommonTemperature,
                'distractor_map' => [$hotBlockHoldsHeat => 'metal_inherently_colder', $onlyFeelMatters => 'confuses_heat_and_temperature'],
                'difficulty' => 3,
            ],
        );

        $heatFlowsOutOfHand = 'Heat flows out of your hand into the ice cube';
        $coldFlowsIntoHand = 'Cold flows out of the ice cube into your hand';

        Question::firstOrCreate(
            ['kssm_code' => '4.1', 'type' => 'mcq', 'body' => 'You pick up an ice cube and your hand feels cold. What is actually happening, physically?'],
            [
                'skill_id' => $thermalEquilibrium->id,
                'options' => [$heatFlowsOutOfHand, $coldFlowsIntoHand],
                'correct_option' => $heatFlowsOutOfHand,
                'distractor_map' => [$coldFlowsIntoHand => 'confuses_heat_and_temperature'],
                'difficulty' => 1,
            ],
        );

        $metalRisesMore = "The metal block's temperature rises more, because it has a lower specific heat capacity than water";
        $bothRiseSame = 'Both blocks rise by the same temperature, since they absorbed the same amount of heat';

        Question::firstOrCreate(
            ['kssm_code' => '4.2', 'type' => 'mcq', 'body' => 'A 1 kg block of water and a 1 kg block of metal both absorb the same amount of heat energy. Which statement is correct?'],
            [
                'skill_id' => $specificHeatCapacity->id,
                'options' => [$metalRisesMore, $bothRiseSame],
                'correct_option' => $metalRisesMore,
                'distractor_map' => [$bothRiseSame => 'confuses_heat_capacity_and_specific_heat_capacity'],
                'difficulty' => 3,
            ],
        );

        $highSpecificHeatCapacity = 'Water has a high specific heat capacity, so it absorbs a lot of heat energy for only a small rise in temperature';
        $largeVolumeAvailable = 'Water has a high heat capacity simply because there is a large volume of it available';

        Question::firstOrCreate(
            ['kssm_code' => '4.2', 'type' => 'mcq', 'body' => 'Why is water used as the coolant in a car radiator rather than a metal like copper?'],
            [
                'skill_id' => $specificHeatCapacity->id,
                'options' => [$highSpecificHeatCapacity, $largeVolumeAvailable],
                'correct_option' => $highSpecificHeatCapacity,
                'distractor_map' => [$largeVolumeAvailable => 'confuses_heat_capacity_and_specific_heat_capacity'],
                'difficulty' => 2,
            ],
        );
    }
}
