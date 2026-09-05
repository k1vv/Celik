<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Segments a homework PDF into per-question units - the step that has to
 * run before HomeworkDiagnosisService can grade anything, since that
 * service grades one question at a time (see engine.md §5, architecture's
 * "Multi-question PDF" plan).
 *
 * Pure extraction/transcription, never grading judgment: this class reads
 * text off a page, it never decides whether an answer is right. The one
 * exception is `suggested_kssm_code` - a proposal, schema-constrained to
 * the four topics in scope, that a teacher reviews and can freely override
 * (never trusted as-is) - curriculum tagging is still ultimately a human
 * decision, same principle as `HomeworkDiagnosisService`'s tag vocabulary.
 */
class HomeworkPdfExtractionService
{
    private const EXTRACT_QUESTIONS_FUNCTION = 'record_extracted_questions';

    private const EXTRACT_ANSWERS_FUNCTION = 'record_extracted_answers';

    /** Closed to the current scope (Form 4 Physics Heat chapter) - see engine.md §7. */
    private const KSSM_CODES_IN_SCOPE = ['4.1', '4.2', '4.3', '4.4'];

    /**
     * Teacher side: segments a blank/unanswered question PDF.
     *
     * @return list<array{order: int, question_text: string, suggested_kssm_code: ?string}>
     */
    public function extractQuestions(string $filePath): array
    {
        $parts = [
            ['inlineData' => [
                'mimeType' => $this->mimeTypeFromExtension($filePath),
                'data' => base64_encode(file_get_contents($filePath)),
            ]],
            ['text' => 'This PDF contains one or more homework questions. Extract each one in '
                .'the order it appears, with your best guess at which KSSM topic it belongs to.'],
        ];

        $model = config('services.gemini.model');

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'tools' => [
                ['functionDeclarations' => [$this->extractQuestionsFunctionDeclaration()]],
            ],
            'toolConfig' => [
                'functionCallingConfig' => [
                    'mode' => 'ANY',
                    'allowedFunctionNames' => [self::EXTRACT_QUESTIONS_FUNCTION],
                ],
            ],
        ])->throw();

        $args = $this->functionCallArgs($response->json());

        return $args['questions'] ?? [];
    }

    /**
     * Student side: segments an answered PDF, matching each answer to one
     * of the assignment's already-known questions (by number and text - the
     * teacher already confirmed these when the assignment was created, so
     * they're passed in as context rather than re-extracted from scratch).
     * `matched: false` means the model couldn't confidently tie a region of
     * the PDF to one of these questions - surfaced to the teacher rather
     * than silently guessed or dropped.
     *
     * @param  list<array{order: int, question_text: string}>  $questions
     * @return list<array{order: int, student_answer_text: ?string, matched: bool}>
     */
    public function extractAnswers(string $filePath, array $questions): array
    {
        $parts = [
            ['inlineData' => [
                'mimeType' => $this->mimeTypeFromExtension($filePath),
                'data' => base64_encode(file_get_contents($filePath)),
            ]],
            ['text' => $this->buildAnswersPrompt($questions)],
        ];

        $model = config('services.gemini.model');

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'tools' => [
                ['functionDeclarations' => [$this->extractAnswersFunctionDeclaration()]],
            ],
            'toolConfig' => [
                'functionCallingConfig' => [
                    'mode' => 'ANY',
                    'allowedFunctionNames' => [self::EXTRACT_ANSWERS_FUNCTION],
                ],
            ],
        ])->throw();

        $args = $this->functionCallArgs($response->json());

        return $args['answers'] ?? [];
    }

    /**
     * @param  list<array{order: int, question_text: string}>  $questions
     */
    private function buildAnswersPrompt(array $questions): string
    {
        $questionList = collect($questions)
            ->map(fn (array $question) => "{$question['order']}. {$question['question_text']}")
            ->implode("\n");

        return "This PDF contains a student's handwritten or typed answers to the "
            ."following questions, in this order:\n\n{$questionList}\n\n"
            .'Read the answer to each question directly from the PDF and transcribe it. '
            ."If you can't confidently tell which question an answer belongs to, mark it unmatched rather than guessing.";
    }

    private function mimeTypeFromExtension(string $filePath): string
    {
        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function extractQuestionsFunctionDeclaration(): array
    {
        return [
            'name' => self::EXTRACT_QUESTIONS_FUNCTION,
            'description' => 'Record each homework question found in the PDF, in order.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'order' => ['type' => 'integer'],
                                'question_text' => ['type' => 'string'],
                                'suggested_kssm_code' => [
                                    'type' => 'string',
                                    'nullable' => true,
                                    'enum' => self::KSSM_CODES_IN_SCOPE,
                                    'description' => 'Best-guess KSSM topic code - a suggestion only, the teacher will confirm or change it.',
                                ],
                            ],
                            'required' => ['order', 'question_text'],
                        ],
                    ],
                ],
                'required' => ['questions'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAnswersFunctionDeclaration(): array
    {
        return [
            'name' => self::EXTRACT_ANSWERS_FUNCTION,
            'description' => "Record the student's answer to each known question, in order.",
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'answers' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'order' => ['type' => 'integer'],
                                'student_answer_text' => ['type' => 'string', 'nullable' => true],
                                'matched' => ['type' => 'boolean'],
                            ],
                            'required' => ['order', 'matched'],
                        ],
                    ],
                ],
                'required' => ['answers'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function functionCallArgs(array $response): array
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $functionCallPart = collect($parts)->firstWhere('functionCall');

        return $functionCallPart['functionCall']['args'] ?? [];
    }
}
