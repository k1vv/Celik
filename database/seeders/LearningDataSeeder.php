<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptivePracticeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a demo Math classroom with a teacher, a handful of students, a
 * skill tree, a bank of questions per difficulty, and a batch of simulated
 * attempts so the teacher dashboard shows a realistic, uneven spread of
 * mastery on first login instead of an empty grid.
 */
class LearningDataSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::create([
            'name' => 'Ms. Amina Yusuf',
            'email' => 'teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
        ]);

        $subject = Subject::create(['name' => 'Mathematics']);

        $skillDefinitions = [
            'Addition' => 'Adding whole numbers up to 3 digits.',
            'Subtraction' => 'Subtracting whole numbers with borrowing.',
            'Multiplication' => 'Multiplying single and double digit numbers.',
            'Fractions' => 'Comparing, adding and simplifying fractions.',
            'Word Problems' => 'Translating word problems into arithmetic.',
        ];

        $skills = collect($skillDefinitions)->map(
            fn ($description, $name) => Skill::create([
                'subject_id' => $subject->id,
                'name' => $name,
                'description' => $description,
            ])
        )->values();

        foreach ($skills as $skill) {
            $this->seedQuestions($skill);
        }

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Grade 5 - Room 3',
            'join_code' => strtoupper(Str::random(6)),
        ]);

        $studentNames = [
            'Chidi Okafor', 'Fatima Bello', 'Liam Carter', 'Zanele Dube',
            'Noah Kim', 'Ayesha Rahman', 'Diego Torres', 'Wanjiru Kamau',
        ];

        $students = collect($studentNames)->map(fn ($name, $i) => User::create([
            'name' => $name,
            'email' => 'student'.($i + 1).'@example.com',
            'password' => 'password',
            'role' => 'student',
        ]));

        $classroom->students()->attach($students->pluck('id'));

        $this->simulateAttempts($students, $skills);
    }

    private function seedQuestions(Skill $skill): void
    {
        foreach (range(1, 5) as $difficulty) {
            foreach (range(1, 2) as $variant) {
                $a = $difficulty * 2 + $variant;
                $b = $difficulty + $variant;

                Question::create([
                    'skill_id' => $skill->id,
                    'body' => "[{$skill->name} - difficulty {$difficulty}] What is {$a} + {$b} * {$difficulty}?",
                    'options' => (function () use ($a, $b, $difficulty) {
                        $correct = $a + $b * $difficulty;

                        return collect([$correct, $correct + 1, $correct - 2, $correct + $difficulty])
                            ->unique()->shuffle()->values()->map(fn ($v) => (string) $v)->all();
                    })(),
                    'correct_option' => (string) ($a + $b * $difficulty),
                    'difficulty' => $difficulty,
                ]);
            }
        }
    }

    private function simulateAttempts($students, $skills): void
    {
        $adaptive = app(AdaptivePracticeService::class);

        // Give each student a hidden "aptitude" per skill so the simulated
        // data produces a believable, uneven spread of class-wide gaps.
        foreach ($students as $student) {
            foreach ($skills as $skill) {
                $aptitude = mt_rand(20, 90) / 100;

                foreach (range(1, mt_rand(6, 14)) as $attemptNumber) {
                    $mastery = $student->masteries()
                        ->where('skill_id', $skill->id)
                        ->first()?->mastery_score ?? 0.3;

                    $difficulty = (int) max(1, min(5, round(1 + $mastery * 4)));
                    $question = Question::where('skill_id', $skill->id)
                        ->where('difficulty', $difficulty)
                        ->inRandomOrder()
                        ->first();

                    if (! $question) {
                        continue;
                    }

                    $isCorrect = (mt_rand() / mt_getrandmax()) < $aptitude;
                    $adaptive->recordAttempt($student, $question, $isCorrect);
                }
            }
        }
    }
}
