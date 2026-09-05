<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentQuestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_assignment_bundles_several_questions_in_order(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'name' => 'Form 4 Physics',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'join_code' => 'TEST01',
        ]);

        $first = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q1']);
        $second = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q2']);

        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach([
            $second->id => ['order' => 2],
            $first->id => ['order' => 1],
        ]);

        $ordered = $assignment->questions()->get();

        $this->assertSame([$first->id, $second->id], $ordered->pluck('id')->all());
    }
}
