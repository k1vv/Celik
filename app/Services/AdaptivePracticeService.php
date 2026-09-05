<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Mastery;
use App\Models\Question;
use App\Models\User;

/**
 * Drives per-student adaptive practice using a lightweight Elo/IRT-style
 * mastery estimate per skill:
 *
 *  - Every (student, skill) pair has a mastery_score in [0, 1], starting
 *    from a weak prior (0.3).
 *  - Each attempt nudges mastery toward the outcome, weighted by how
 *    surprising the outcome was given the question's difficulty
 *    (logistic "expected probability of success", exactly like an Elo
 *    expected-score calculation). Getting an easy question right barely
 *    moves the needle; getting a hard one right moves it a lot.
 *  - Question selection is weakest-skill-first (the skill the student is
 *    least likely to have mastered), with a small chance of interleaving
 *    a review of an already-strong skill for spaced repetition, and a
 *    difficulty picked to match the student's current mastery band so
 *    they're neither bored nor stuck.
 *
 * This keeps the model fully explainable (no black-box ML, nothing that
 * needs training data or a GPU) while still being genuinely adaptive -
 * a good fit for low-resource classrooms and a hackathon timeline.
 */
class AdaptivePracticeService
{
    /** How fast a single attempt can move a mastery score. */
    private const LEARNING_RATE = 0.18;

    /** Chance of interleaving a review question instead of the weakest skill. */
    private const REVIEW_CHANCE = 0.2;

    /** Mastery score below which a skill counts as a "gap" for the teacher dashboard. */
    public const STRUGGLING_THRESHOLD = 0.5;

    /**
     * Pick the next question for a student within a classroom's subject.
     */
    public function nextQuestionFor(User $student, Classroom $classroom): ?Question
    {
        $skills = $classroom->subject->skills;

        if ($skills->isEmpty()) {
            return null;
        }

        $masteries = $skills->mapWithKeys(function ($skill) use ($student) {
            $mastery = Mastery::firstOrCreate(
                ['user_id' => $student->id, 'skill_id' => $skill->id],
                ['mastery_score' => 0.3],
            );

            return [$skill->id => $mastery];
        });

        $skill = $this->chooseSkill($skills, $masteries);
        $mastery = $masteries[$skill->id];

        return $this->pickQuestionForDifficulty($skill, $mastery->mastery_score);
    }

    /**
     * Record the outcome of an attempt and update the student's mastery estimate.
     */
    public function recordAttempt(User $student, Question $question, bool $isCorrect): Attempt
    {
        $mastery = Mastery::firstOrCreate(
            ['user_id' => $student->id, 'skill_id' => $question->skill_id],
            ['mastery_score' => 0.3],
        );

        $before = $mastery->mastery_score;
        $difficultyNorm = ($question->difficulty - 1) / 4; // 1..5 -> 0..1

        // Logistic "expected probability of success" given mastery vs difficulty.
        $expected = 1 / (1 + exp(-6 * ($before - $difficultyNorm)));
        $after = $before + self::LEARNING_RATE * (($isCorrect ? 1 : 0) - $expected);
        $after = max(0.0, min(1.0, $after));

        $mastery->update([
            'mastery_score' => $after,
            'attempts_count' => $mastery->attempts_count + 1,
            'correct_count' => $mastery->correct_count + ($isCorrect ? 1 : 0),
            'last_attempt_at' => now(),
        ]);

        return Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'skill_id' => $question->skill_id,
            'is_correct' => $isCorrect,
            'difficulty_at_attempt' => $question->difficulty,
            'mastery_before' => $before,
            'mastery_after' => $after,
        ]);
    }

    private function chooseSkill($skills, $masteries)
    {
        $masteredSkills = $skills->filter(
            fn ($skill) => $masteries[$skill->id]->mastery_score >= self::STRUGGLING_THRESHOLD
        );

        if ($masteredSkills->isNotEmpty() && (mt_rand() / mt_getrandmax()) < self::REVIEW_CHANCE) {
            return $masteredSkills->random();
        }

        return $skills->sortBy(fn ($skill) => $masteries[$skill->id]->mastery_score)->first();
    }

    private function pickQuestionForDifficulty(\App\Models\Skill $skill, float $masteryScore): ?Question
    {
        $targetDifficulty = (int) max(1, min(5, round(1 + $masteryScore * 4)));

        $question = Question::where('skill_id', $skill->id)
            ->where('difficulty', $targetDifficulty)
            ->inRandomOrder()
            ->first();

        if ($question) {
            return $question;
        }

        // Fall back to the closest difficulty available for this skill.
        return Question::where('skill_id', $skill->id)
            ->orderByRaw('ABS(difficulty - ?)', [$targetDifficulty])
            ->inRandomOrder()
            ->first();
    }
}
