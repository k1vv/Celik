<?php

namespace Database\Seeders\GoldSets;

/**
 * Hand-authored gold-set for `php artisan homework:evaluate`, covering the
 * two Heat-chapter topics that have real grounding so far (4.1 Thermal
 * Equilibrium, 4.2 Specific Heat Capacity - see TopicGroundingSeeder).
 *
 * Every answer is written by construction: the gold label is known because
 * the answer was deliberately written to embed exactly that gap (or none),
 * not inferred by grading the text after the fact. That's a real
 * independent ground truth - asking any LLM, including a different one, to
 * assign the label instead would just be using AI to validate AI. Review
 * each entry's label against its answer text before trusting a
 * `homework:evaluate` run that uses this.
 */
class HeatHomeworkGoldSet
{
    /**
     * @return list<array{
     *     kssm_code: string,
     *     label: string,
     *     answer: string,
     *     gold: array{correct: bool, conceptual_tag: ?string, method_issue: ?string, reasoning_issue: ?string, expected_needs_review: bool},
     * }>
     */
    public static function entries(): array
    {
        return [
            ...self::thermalEquilibrium(),
            ...self::specificHeatCapacity(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function thermalEquilibrium(): array
    {
        return [
            [
                'kssm_code' => '4.1',
                'label' => 'correct',
                'answer' => 'The metal spoon feels colder because metal conducts heat away from your '
                    .'hand much faster than wood does. Both spoons have actually been on the table '
                    .'overnight and are at the same room temperature - they are in thermal equilibrium '
                    .'- it is only the rate of heat conduction that differs, not their temperature.',
                'gold' => [
                    'correct' => true,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.1',
                'label' => 'conceptual: metal_inherently_colder',
                'answer' => 'The metal spoon is colder because metal is naturally a colder material '
                    .'than wood, even when they have been left in the same place for the same amount '
                    .'of time.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => 'metal_inherently_colder',
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.1',
                'label' => 'conceptual: confuses_heat_and_temperature',
                'answer' => 'The metal spoon has less heat inside it than the wooden spoon, that is '
                    .'why it feels cold to touch.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => 'confuses_heat_and_temperature',
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.1',
                'label' => 'method: no_direction_of_heat_flow_stated',
                'answer' => 'Both spoons are actually at the same temperature. Metal just feels colder '
                    .'than wood because of how well each material conducts heat.',
                'gold' => [
                    'correct' => true,
                    'conceptual_tag' => null,
                    'method_issue' => 'no_direction_of_heat_flow_stated',
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.1',
                'label' => 'reasoning: reversed_cause_and_effect',
                'answer' => 'The metal spoon is cold, so heat flows out of the wooden spoon and into '
                    .'the metal spoon, which is what makes the metal spoon feel cold to touch.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => 'reversed_cause_and_effect',
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.1',
                'label' => 'ambiguous - should be flagged for review',
                'answer' => 'Umm, I think it is something to do with heat and temperature and maybe '
                    .'conduction? Not really sure but the metal one just feels different.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => true,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function specificHeatCapacity(): array
    {
        return [
            [
                'kssm_code' => '4.2',
                'label' => 'correct',
                'answer' => 'Using c = Q / (mΔθ): c = 9000 / (2 × 10) = 450 J kg⁻¹ °C⁻¹.',
                'gold' => [
                    'correct' => true,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.2',
                'label' => 'conceptual: confuses_heat_capacity_and_specific_heat_capacity',
                'answer' => 'The heat capacity of this metal block is 450 J kg⁻¹ °C⁻¹, so the whole '
                    .'block always needs 450 J to raise its temperature by 1°C no matter how heavy it is.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => 'confuses_heat_capacity_and_specific_heat_capacity',
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.2',
                'label' => 'method: missing_units',
                'answer' => 'c = 9000 / (2 × 10) = 450',
                'gold' => [
                    'correct' => true,
                    'conceptual_tag' => null,
                    'method_issue' => 'missing_units',
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.2',
                'label' => 'method: wrong_formula_variables',
                'answer' => 'c = Q / Δθ = 9000 / 10 = 900 J kg⁻¹ °C⁻¹.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => null,
                    'method_issue' => 'wrong_formula_variables',
                    'reasoning_issue' => null,
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.2',
                'label' => 'reasoning: ignores_mass_dependence',
                'answer' => 'The temperature rise is 10°C for 9000 J absorbed, so the specific heat '
                    .'capacity is 900 J kg⁻¹ °C⁻¹ - the mass of the block does not matter for this.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => 'ignores_mass_dependence',
                    'expected_needs_review' => false,
                ],
            ],
            [
                'kssm_code' => '4.2',
                'label' => 'ambiguous - should be flagged for review',
                'answer' => 'I plugged the numbers into the formula and got something around '
                    .'400-something but I am not sure if I used the mass correctly.',
                'gold' => [
                    'correct' => false,
                    'conceptual_tag' => null,
                    'method_issue' => null,
                    'reasoning_issue' => null,
                    'expected_needs_review' => true,
                ],
            ],
        ];
    }
}
