<?php

namespace Tests\Feature;

use App\Models\TopicGrounding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicGroundingLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_the_grounding_for_a_known_kssm_code(): void
    {
        TopicGrounding::create([
            'kssm_code' => '9.2.2',
            'syllabus_excerpt' => 'Heat is the transfer of thermal energy from a hotter to a cooler object.',
            'rubric' => 'Full marks require distinguishing heat (energy transferred) from temperature (a measure of average kinetic energy).',
            'tag_vocabulary' => ['conceptual' => ['metal_inherently_colder']],
        ]);

        $grounding = TopicGrounding::forKssmCode('9.2.2');

        $this->assertNotNull($grounding);
        $this->assertSame('Heat is the transfer of thermal energy from a hotter to a cooler object.', $grounding->syllabus_excerpt);
        $this->assertSame(['metal_inherently_colder'], $grounding->tag_vocabulary['conceptual']);
    }

    public function test_it_returns_null_for_an_unknown_kssm_code(): void
    {
        $this->assertNull(TopicGrounding::forKssmCode('does-not-exist'));
    }
}
