<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\PracticeSet;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeSetAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_answering_a_question_from_within_a_practice_set_records_which_set_it_belongs_to(): void
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

        $this->actingAs($student)->post('/practice', [
            'question_id' => $question->id,
            'selected_option' => 'A',
            'practice_set_id' => $set->id,
        ]);

        $attempt = $student->attempts()->where('question_id', $question->id)->firstOrFail();
        $this->assertSame($set->id, $attempt->practice_set_id);
    }

    public function test_submitting_a_practice_set_answer_for_a_set_outside_the_students_classroom_returns_403(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherClassroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Someone Else\'s Class',
            'join_code' => 'PHYS02',
        ]);

        $question = Question::create(['skill_id' => $skill->id, 'body' => 'Q1', 'options' => ['A', 'B'], 'correct_option' => 'A']);
        $set = PracticeSet::create(['classroom_id' => $otherClassroom->id, 'skill_id' => $skill->id, 'title' => 'Heat Transfer Foundations']);
        $set->questions()->attach($question->id, ['order' => 0]);

        // This student isn't in $otherClassroom at all.
        $student = User::factory()->create(['role' => 'student']);
        $myClassroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'My Class',
            'join_code' => 'PHYS03',
        ]);
        $myClassroom->students()->attach($student);

        $response = $this->actingAs($student)->post('/practice', [
            'question_id' => $question->id,
            'selected_option' => 'A',
            'practice_set_id' => $set->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('attempts', ['question_id' => $question->id]);
    }
}
