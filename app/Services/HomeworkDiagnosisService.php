<?php

namespace App\Services;

use App\Models\Question;
use App\Models\TopicGrounding;
use Illuminate\Support\Facades\Http;

/**
 * Diagnoses an open-ended homework answer across the four dimensions
 * (Conceptual, Curriculum, Method, Reasoning) - see engine.md §5.
 *
 * Unlike the quiz path, there are no distractors to pre-map, so this needs
 * a live LLM call. Two things keep it from being "just prompting":
 *  - it's grounded in a retrieved syllabus excerpt + rubric (RAG), not the
 *    model's own memory of the syllabus
 *  - it forces the model into a fixed schema via Gemini function calling
 *    (mode ANY, restricted to this one function), so the output is a
 *    validated structure rather than parsed free text
 *
 * Curriculum mastery is derived locally from the question's own kssm_code
 * and the correctness result, the same way the quiz path treats curriculum
 * as pulled-from-metadata rather than AI-judged - the model isn't asked to
 * report a fact it can only get from data it already has.
 */
class HomeworkDiagnosisService
{
    private const DIAGNOSIS_FUNCTION = 'record_diagnosis';

    /** Fixed rubric total every homework answer is marked out of. */
    private const MAX_SCORE = 20;

    /** Confidence below which a diagnosis is flagged for teacher review instead of trusted - see engine.md §5. */
    public const CONFIDENCE_THRESHOLD = 0.6;

    /**
     * Fraction of homework attempts spot-checked by a teacher regardless of
     * confidence - the review queue otherwise only ever contains cases the
     * model itself flagged, which can never catch a confidently-wrong
     * diagnosis or calibrate CONFIDENCE_THRESHOLD against real grading.
     */
    private const AUDIT_SAMPLE_RATE = 0.1;

    /**
     * @return array{
     *     correct: bool,
     *     conceptual: array{gap: bool, tag: ?string},
     *     curriculum: array{kssm_code: ?string, mastered: bool},
     *     method: array{issue: ?string},
     *     reasoning: array{issue: ?string},
     *     confidence: float,
     * }
     */
    public function diagnose(Question $question, string $rawAnswer): array
    {
        $grounding = $this->groundingFor($question);
        $parts = [['text' => $this->buildPrompt($question, $grounding, $rawAnswer)]];

        return $this->runDiagnosis($question, $grounding, $parts, $this->objectiveCheck($question, $rawAnswer));
    }

    /**
     * Diagnoses a photographed/handwritten homework answer - Gemini reads
     * the image or PDF directly, no separate OCR step, per engine.md §5.
     * There's no transcribed text to run objectiveCheck() against, so
     * correctness for a photographed answer is always the model's own
     * claim - same as a typed answer to a question with no expected_answer.
     *
     * @return array<string, mixed>
     */
    public function diagnosePhoto(Question $question, string $filePath): array
    {
        $grounding = $this->groundingFor($question);
        $parts = [
            ['inlineData' => [
                'mimeType' => $this->mimeTypeFromExtension($filePath),
                'data' => base64_encode(file_get_contents($filePath)),
            ]],
            ['text' => $this->buildPhotoPrompt($question, $grounding)],
        ];

        return $this->runDiagnosis($question, $grounding, $parts, null);
    }

    /**
     * By extension rather than file content - the controller only ever
     * stores an upload after validating it's one of these extensions, so
     * this is deterministic and doesn't depend on the file's magic bytes.
     */
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
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function runDiagnosis(Question $question, ?TopicGrounding $grounding, array $parts, ?bool $objectiveCorrect): array
    {
        $input = $this->callGemini($question, $grounding, $parts);
        $diagnosis = $this->toDiagnosis($question, $input, $objectiveCorrect);

        // A confidently-wrong diagnosis is the one gap self-reported
        // confidence can't catch - a second opinion can. Calls already
        // headed for review skip this: doubling the cost there buys nothing.
        if (! $diagnosis['needs_review']) {
            $secondOpinion = $this->callGemini($question, $grounding, $parts);

            if ($this->disagree($input, $secondOpinion)) {
                $diagnosis['needs_review'] = true;
            }
        }

        $diagnosis['sampled_for_audit'] = $this->rollForAudit();

        return $diagnosis;
    }

    private function groundingFor(Question $question): ?TopicGrounding
    {
        return $question->kssm_code ? TopicGrounding::forKssmCode($question->kssm_code) : null;
    }

    /**
     * Mirrors AdaptivePracticeService's mt_rand()-based REVIEW_CHANCE
     * pattern. Protected so tests can force a deterministic outcome via a
     * partial mock instead of asserting on raw randomness.
     */
    protected function rollForAudit(): bool
    {
        return (mt_rand() / mt_getrandmax()) < self::AUDIT_SAMPLE_RATE;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<string, mixed>
     */
    private function callGemini(Question $question, ?TopicGrounding $grounding, array $parts): array
    {
        $model = config('services.gemini.model');

        $response = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini.key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            'contents' => [
                ['role' => 'user', 'parts' => $parts],
            ],
            'tools' => [
                ['functionDeclarations' => [$this->diagnosisFunctionDeclaration($grounding)]],
            ],
            'toolConfig' => [
                'functionCallingConfig' => [
                    'mode' => 'ANY',
                    'allowedFunctionNames' => [self::DIAGNOSIS_FUNCTION],
                ],
            ],
        ])->throw();

        return $this->functionCallArgs($response->json());
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    private function disagree(array $first, array $second): bool
    {
        $firstCorrect = (bool) ($first['correct'] ?? false);
        $secondCorrect = (bool) ($second['correct'] ?? false);

        return $firstCorrect !== $secondCorrect
            || ($first['conceptual_tag'] ?? null) !== ($second['conceptual_tag'] ?? null);
    }

    /**
     * Deterministic correctness check for a question with a known expected
     * value - no AI needed for correctness itself, see engine.md §5. Null
     * means the question has no objective sub-part to check this way.
     */
    private function objectiveCheck(Question $question, string $rawAnswer): ?bool
    {
        if ($question->expected_answer === null) {
            return null;
        }

        return trim(mb_strtolower($rawAnswer)) === trim(mb_strtolower($question->expected_answer));
    }

    private function buildPrompt(Question $question, ?TopicGrounding $grounding, string $rawAnswer): string
    {
        return implode("\n\n", array_filter([
            "Question: {$question->body}",
            $grounding ? "Syllabus excerpt: {$grounding->syllabus_excerpt}" : null,
            $grounding ? "Rubric: {$grounding->rubric}" : null,
            $grounding ? 'Allowed tags: '.json_encode($grounding->tag_vocabulary) : null,
            "Student's answer: {$rawAnswer}",
        ]));
    }

    private function buildPhotoPrompt(Question $question, ?TopicGrounding $grounding): string
    {
        return implode("\n\n", array_filter([
            "Question: {$question->body}",
            $grounding ? "Syllabus excerpt: {$grounding->syllabus_excerpt}" : null,
            $grounding ? "Rubric: {$grounding->rubric}" : null,
            $grounding ? 'Allowed tags: '.json_encode($grounding->tag_vocabulary) : null,
            "The student's handwritten answer is attached as an image/PDF. Read it directly and diagnose it the same way you would a typed answer.",
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosisFunctionDeclaration(?TopicGrounding $grounding): array
    {
        return [
            'name' => self::DIAGNOSIS_FUNCTION,
            'description' => "Record a structured diagnosis of a student's homework answer.",
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'correct' => ['type' => 'boolean'],
                    'conceptual_tag' => $this->tagProperty($grounding, 'conceptual'),
                    'conceptual_evidence' => $this->evidenceProperty(),
                    'method_issue' => $this->tagProperty($grounding, 'method'),
                    'method_evidence' => $this->evidenceProperty(),
                    'reasoning_issue' => $this->tagProperty($grounding, 'reasoning'),
                    'reasoning_evidence' => $this->evidenceProperty(),
                    'confidence' => ['type' => 'number'],
                    'score' => [
                        'type' => 'integer',
                        'description' => 'Mark out of '.self::MAX_SCORE.", following the rubric's mark scheme - not just a correct/incorrect flag; partial credit is expected for a partially-correct answer.",
                    ],
                ],
                'required' => ['correct', 'confidence', 'score'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tagProperty(?TopicGrounding $grounding, string $dimension): array
    {
        $property = ['type' => 'string', 'nullable' => true];

        $vocabulary = $grounding?->tag_vocabulary[$dimension] ?? null;

        if ($vocabulary) {
            $property['enum'] = $vocabulary;
        }

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    private function evidenceProperty(): array
    {
        return [
            'type' => 'string',
            'nullable' => true,
            'description' => "The exact phrase from the student's answer that justifies this finding.",
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

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function toDiagnosis(Question $question, array $input, ?bool $objectiveCorrect): array
    {
        $correct = $objectiveCorrect ?? (bool) ($input['correct'] ?? false);
        $confidence = (float) ($input['confidence'] ?? 0.0);

        return [
            'correct' => $correct,
            'conceptual' => [
                'gap' => ! empty($input['conceptual_tag']),
                'tag' => $input['conceptual_tag'] ?? null,
                'evidence' => $input['conceptual_evidence'] ?? null,
            ],
            'curriculum' => [
                'kssm_code' => $question->kssm_code,
                'mastered' => $correct,
            ],
            'method' => [
                'issue' => $input['method_issue'] ?? null,
                'evidence' => $input['method_evidence'] ?? null,
            ],
            'reasoning' => [
                'issue' => $input['reasoning_issue'] ?? null,
                'evidence' => $input['reasoning_evidence'] ?? null,
            ],
            'confidence' => $confidence,
            'needs_review' => $confidence < self::CONFIDENCE_THRESHOLD,
            'score' => isset($input['score']) ? (int) $input['score'] : null,
            'max_score' => self::MAX_SCORE,
        ];
    }
}
