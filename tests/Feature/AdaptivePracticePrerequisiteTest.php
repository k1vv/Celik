<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Mastery;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use App\Services\AdaptivePracticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptivePracticePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_weak_on_a_skill_and_its_prerequisite_is_served_the_prerequisite_next(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::create(['name' => 'Physics']);

        $thermalEquilibrium = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $specificHeatCapacity = Skill::create(['subject_id' => $subject->id, 'name' => 'Specific Heat Capacity']);
        $specificHeatCapacity->prerequisites()->attach($thermalEquilibrium);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'ABC123',
        ]);

        foreach ([$thermalEquilibrium, $specificHeatCapacity] as $skill) {
            Question::create([
                'skill_id' => $skill->id,
                'body' => 'Question on '.$skill->name,
                'options' => ['A', 'B'],
                'correct_option' => 'A',
                'difficulty' => 3,
            ]);
        }

        // The downstream skill is weaker than its prerequisite - naive
        // weakest-first would pick it, but both are below the struggling
        // threshold, so practice should target the prerequisite instead.
        Mastery::create(['user_id' => $student->id, 'skill_id' => $specificHeatCapacity->id, 'mastery_score' => 0.1]);
        Mastery::create(['user_id' => $student->id, 'skill_id' => $thermalEquilibrium->id, 'mastery_score' => 0.4]);

        $question = (new AdaptivePracticeService)->nextQuestionFor($student, $classroom);

        $this->assertSame($thermalEquilibrium->id, $question->skill_id);
    }
}
