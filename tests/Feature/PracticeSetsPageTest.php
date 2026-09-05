<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\PracticeSet;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeSetsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_released_practice_set_with_no_attempts_yet_appears_with_zero_progress(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Cikgu Aiman']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $questionOne = Question::create(['skill_id' => $skill->id, 'body' => 'Q1', 'options' => ['A', 'B'], 'correct_option' => 'A']);
        $questionTwo = Question::create(['skill_id' => $skill->id, 'body' => 'Q2', 'options' => ['A', 'B'], 'correct_option' => 'A']);

        $set = PracticeSet::create([
            'classroom_id' => $classroom->id,
            'skill_id' => $skill->id,
            'title' => 'Heat Transfer Foundations',
            'description' => 'Strengthen your understanding of conduction.',
            'difficulty' => 'Foundation',
        ]);
        $set->questions()->attach([$questionOne->id => ['order' => 0], $questionTwo->id => ['order' => 1]]);

        $response = $this->actingAs($student)->get('/practice');

        $response->assertInertia(fn ($page) => $page
            ->component('Practice')
            ->has('practiceSets', 1)
            ->where('practiceSets.0.id', $set->id)
            ->where('practiceSets.0.title', 'Heat Transfer Foundations')
            ->where('practiceSets.0.subject', 'Physics')
            ->where('practiceSets.0.teacher', 'Cikgu Aiman')
            ->where('practiceSets.0.description', 'Strengthen your understanding of conduction.')
            ->where('practiceSets.0.difficulty', 'Foundation')
            ->where('practiceSets.0.questionCount', 2)
            ->where('practiceSets.0.progress', 0)
            ->where('practiceSets.0.status', 'assigned')
        );
    }

    public function test_a_practice_set_with_one_of_two_questions_answered_shows_50_percent_progress(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $questionOne = Question::create(['skill_id' => $skill->id, 'body' => 'Q1', 'options' => ['A', 'B'], 'correct_option' => 'A']);
        $questionTwo = Question::create(['skill_id' => $skill->id, 'body' => 'Q2', 'options' => ['A', 'B'], 'correct_option' => 'A']);

        $set = PracticeSet::create([
            'classroom_id' => $classroom->id,
            'skill_id' => $skill->id,
            'title' => 'Heat Transfer Foundations',
        ]);
        $set->questions()->attach([$questionOne->id => ['order' => 0], $questionTwo->id => ['order' => 1]]);

        Attempt::create([
            'user_id' => $student->id,
            'question_id' => $questionOne->id,
            'practice_set_id' => $set->id,
            'skill_id' => $skill->id,
            'is_correct' => true,
        ]);

        $response = $this->actingAs($student)->get('/practice');

        $response->assertInertia(fn ($page) => $page
            ->component('Practice')
            ->where('practiceSets.0.progress', 50)
            ->where('practiceSets.0.status', 'in_progress')
        );
    }

    public function test_a_practice_set_surfaces_the_students_own_open_diagnoses_on_its_skill_as_gaps(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);
        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);

        $question = Question::create(['skill_id' => $skill->id, 'body' => 'Q1', 'options' => ['A', 'B'], 'correct_option' => 'A']);
        $set = PracticeSet::create(['classroom_id' => $classroom->id, 'skill_id' => $skill->id, 'title' => 'Heat Transfer Foundations']);
        $set->questions()->attach($question->id, ['order' => 0]);

        $pastAttempt = Attempt::create(['user_id' => $student->id, 'question_id' => $question->id, 'skill_id' => $skill->id, 'is_correct' => false]);
        $pastAttempt->diagnoses()->create(['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder']);

        $response = $this->actingAs($student)->get('/practice');

        $response->assertInertia(fn ($page) => $page
            ->component('Practice')
            ->where('practiceSets.0.gaps', [
                ['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder'],
            ])
        );
    }
}
