<?php

namespace Tests\Feature;

use App\Services\HomeworkPdfExtractionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeworkPdfExtractionServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $args
     */
    private function geminiResponse(array $args): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'record_extracted_questions', 'args' => $args]],
                ]]],
            ],
        ];
    }

    public function test_it_extracts_questions_with_a_suggested_kssm_code_from_a_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'homework').'.pdf';
        file_put_contents($path, 'fake-pdf-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse([
                'questions' => [
                    ['order' => 1, 'question_text' => 'Explain thermal equilibrium.', 'suggested_kssm_code' => '4.1'],
                    ['order' => 2, 'question_text' => 'Calculate specific heat capacity.', 'suggested_kssm_code' => '4.2'],
                ],
            ]), 200),
        ]);

        $questions = (new HomeworkPdfExtractionService)->extractQuestions($path);

        $this->assertSame([
            ['order' => 1, 'question_text' => 'Explain thermal equilibrium.', 'suggested_kssm_code' => '4.1'],
            ['order' => 2, 'question_text' => 'Calculate specific heat capacity.', 'suggested_kssm_code' => '4.2'],
        ], $questions);

        unlink($path);
    }

    public function test_it_sends_the_pdf_as_inline_data_and_forces_the_extraction_function(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'homework').'.pdf';
        file_put_contents($path, 'fake-pdf-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse(['questions' => []]), 200),
        ]);

        (new HomeworkPdfExtractionService)->extractQuestions($path);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];
            $filePart = collect($parts)->firstWhere('inlineData');

            return $filePart['inlineData']['mimeType'] === 'application/pdf'
                && $filePart['inlineData']['data'] === base64_encode('fake-pdf-bytes')
                && $request['toolConfig']['functionCallingConfig']['allowedFunctionNames'][0] === 'record_extracted_questions';
        });

        unlink($path);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function geminiAnswersResponse(array $args): array
    {
        return [
            'candidates' => [
                ['content' => ['parts' => [
                    ['functionCall' => ['name' => 'record_extracted_answers', 'args' => $args]],
                ]]],
            ],
        ];
    }

    public function test_it_extracts_answers_matched_to_the_known_questions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'homework').'.pdf';
        file_put_contents($path, 'fake-pdf-bytes');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response($this->geminiAnswersResponse([
                'answers' => [
                    ['order' => 1, 'student_answer_text' => 'Heat flows until both reach the same temperature.', 'matched' => true],
                    ['order' => 2, 'student_answer_text' => null, 'matched' => false],
                ],
            ]), 200),
        ]);

        $questions = [
            ['order' => 1, 'question_text' => 'Explain thermal equilibrium.'],
            ['order' => 2, 'question_text' => 'Calculate specific heat capacity.'],
        ];

        $answers = (new HomeworkPdfExtractionService)->extractAnswers($path, $questions);

        $this->assertSame([
            ['order' => 1, 'student_answer_text' => 'Heat flows until both reach the same temperature.', 'matched' => true],
            ['order' => 2, 'student_answer_text' => null, 'matched' => false],
        ], $answers);

        unlink($path);
    }
}
