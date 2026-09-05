<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\TopicGrounding;
use App\Services\HomeworkDiagnosisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class HomeworkDiagnosisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(): Question
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);

        TopicGrounding::create([
            'kssm_code' => '9.2.2',
            'syllabus_excerpt' => 'Heat is the transfer of thermal energy from a hotter to a cooler object.',
            'rubric' => 'Full marks require distinguishing heat from temperature.',
            'tag_vocabulary' => [
                'conceptual' => ['metal_inherently_colder'],
                'method' => ['missed_calculation_step'],
                'reasoning' => ['reversed_cause_and_effect'],
            ],
        ]);

        return Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '9.2.2',
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function geminiResponse(array $args): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'record_diagnosis', 'args' => $args]],
                ]]],
            ],
        ];
    }

    public function test_it_parses_a_grounded_llm_response_into_a_structured_diagnosis(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false,
                'conceptual_tag' => 'metal_inherently_colder',
                'method_issue' => null,
                'reasoning_issue' => 'Does not explain the direction of heat transfer.',
                'confidence' => 0.82,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal is just colder than wood.');

        $this->assertFalse($diagnosis['correct']);
        $this->assertTrue($diagnosis['conceptual']['gap']);
        $this->assertSame('metal_inherently_colder', $diagnosis['conceptual']['tag']);
        $this->assertSame('9.2.2', $diagnosis['curriculum']['kssm_code']);
        $this->assertFalse($diagnosis['curriculum']['mastered']);
        $this->assertNull($diagnosis['method']['issue']);
        $this->assertSame('Does not explain the direction of heat transfer.', $diagnosis['reasoning']['issue']);
        $this->assertSame(0.82, $diagnosis['confidence']);
    }

    public function test_it_carries_a_rubric_based_mark_out_of_20_through_the_diagnosis(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false,
                'conceptual_tag' => 'metal_inherently_colder',
                'confidence' => 0.82,
                'score' => 14,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal is just colder than wood.');

        $this->assertSame(14, $diagnosis['score']);
        $this->assertSame(20, $diagnosis['max_score']);
    }

    public function test_a_low_confidence_diagnosis_is_flagged_for_review_instead_of_trusted(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false, 'conceptual_tag' => 'metal_inherently_colder', 'confidence' => 0.35,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Not sure, maybe metal is colder?');

        $this->assertTrue($diagnosis['needs_review']);
    }

    public function test_a_high_confidence_diagnosis_is_not_flagged_for_review(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false, 'conceptual_tag' => 'metal_inherently_colder', 'confidence' => 0.82,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal is just colder than wood.');

        $this->assertFalse($diagnosis['needs_review']);
    }

    public function test_a_wrong_objective_answer_overrides_a_llm_claim_of_correct(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Specific Heat Capacity']);

        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Calculate the specific heat capacity of the metal.',
            'expected_answer' => '450',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => true, 'confidence' => 0.9,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, '500');

        $this->assertFalse($diagnosis['correct']);
    }

    public function test_it_restricts_diagnosis_tags_to_the_groundings_vocabulary(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => true, 'confidence' => 0.9,
            ]), 200),
        ]);

        (new HomeworkDiagnosisService)->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        Http::assertSent(function ($request) {
            $properties = $request['tools'][0]['functionDeclarations'][0]['parameters']['properties'];

            return $properties['conceptual_tag']['enum'] === ['metal_inherently_colder']
                && $properties['method_issue']['enum'] === ['missed_calculation_step']
                && $properties['reasoning_issue']['enum'] === ['reversed_cause_and_effect'];
        });
    }

    public function test_it_carries_quoted_evidence_through_for_each_dimension(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false,
                'conceptual_tag' => 'metal_inherently_colder',
                'conceptual_evidence' => 'Metal is just colder than wood.',
                'method_issue' => null,
                'reasoning_issue' => 'Does not explain the direction of heat transfer.',
                'reasoning_evidence' => 'Metal is just colder than wood.',
                'confidence' => 0.82,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal is just colder than wood.');

        $this->assertSame('Metal is just colder than wood.', $diagnosis['conceptual']['evidence']);
        $this->assertNull($diagnosis['method']['evidence']);
        $this->assertSame('Metal is just colder than wood.', $diagnosis['reasoning']['evidence']);
    }

    public function test_two_disagreeing_high_confidence_calls_are_flagged_for_review(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiResponse(['correct' => true, 'confidence' => 0.9]), 200)
                ->push($this->geminiResponse(['correct' => false, 'confidence' => 0.9]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        $this->assertTrue($diagnosis['needs_review']);
    }

    public function test_two_agreeing_high_confidence_calls_stay_trusted(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiResponse(['correct' => true, 'confidence' => 0.9]), 200)
                ->push($this->geminiResponse(['correct' => true, 'confidence' => 0.9]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        $this->assertFalse($diagnosis['needs_review']);
    }

    public function test_a_sampled_attempt_is_flagged_for_audit_even_when_trusted(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => true, 'confidence' => 0.9,
            ]), 200),
        ]);

        $service = Mockery::mock(HomeworkDiagnosisService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('rollForAudit')->andReturn(true);

        $diagnosis = $service->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        $this->assertTrue($diagnosis['sampled_for_audit']);
        $this->assertFalse($diagnosis['needs_review']);
    }

    public function test_an_unsampled_attempt_is_not_flagged_for_audit(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => true, 'confidence' => 0.9,
            ]), 200),
        ]);

        $service = Mockery::mock(HomeworkDiagnosisService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('rollForAudit')->andReturn(false);

        $diagnosis = $service->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        $this->assertFalse($diagnosis['sampled_for_audit']);
    }

    public function test_it_diagnoses_a_photographed_answer_by_sending_inline_image_data(): void
    {
        $question = $this->makeQuestion();

        $path = tempnam(sys_get_temp_dir(), 'homework').'.png';
        file_put_contents($path, 'fake-png-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => false,
                'conceptual_tag' => 'metal_inherently_colder',
                'confidence' => 0.9,
            ]), 200),
        ]);

        $diagnosis = (new HomeworkDiagnosisService)->diagnosePhoto($question, $path);

        $this->assertFalse($diagnosis['correct']);
        $this->assertSame('metal_inherently_colder', $diagnosis['conceptual']['tag']);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];
            $imagePart = collect($parts)->firstWhere('inlineData');

            return $imagePart['inlineData']['mimeType'] === 'image/png'
                && $imagePart['inlineData']['data'] === base64_encode('fake-png-bytes');
        });

        unlink($path);
    }

    public function test_it_sends_the_grounding_and_forces_the_diagnosis_function(): void
    {
        $question = $this->makeQuestion();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'correct' => true, 'confidence' => 0.9,
            ]), 200),
        ]);

        (new HomeworkDiagnosisService)->diagnose($question, 'Metal conducts heat away from the hand faster than wood.');

        Http::assertSent(function ($request) use ($question) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && str_contains($request->url(), ':generateContent')
                && $request['toolConfig']['functionCallingConfig']['allowedFunctionNames'][0] === 'record_diagnosis'
                && str_contains($request['contents'][0]['parts'][0]['text'], $question->body)
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Heat is the transfer of thermal energy');
        });
    }
}
