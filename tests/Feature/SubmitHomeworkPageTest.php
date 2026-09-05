<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitHomeworkPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_assignments_for_the_students_own_classroom_and_their_own_past_attempts(): void
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'PHYS01',
        ]);

        $question = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '4.1',
        ]);
        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'title' => 'Homework 1',
            'due_at' => now()->addWeek(),
        ]);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        $student = User::factory()->create(['role' => 'student']);
        $classroom->students()->attach($student);
        $otherStudent = User::factory()->create(['role' => 'student']);

        $attempt = Attempt::create([
            'user_id' => $student->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'raw_answer' => 'Metal is naturally colder than wood.',
            'is_correct' => false,
            'confidence' => 0.9,
            'needs_review' => false,
            'sampled_for_audit' => false,
        ]);
        $attempt->diagnoses()->create([
            'dimension' => 'conceptual',
            'tag' => 'metal_inherently_colder',
            'evidence' => 'Metal is naturally colder than wood.',
        ]);

        // A different student's attempt must not leak into this student's history.
        Attempt::create([
            'user_id' => $otherStudent->id,
            'question_id' => $question->id,
            'skill_id' => $skill->id,
            'source' => 'homework',
            'is_correct' => true,
            'confidence' => 1.0,
            'needs_review' => false,
            'sampled_for_audit' => false,
        ]);

        // A homework question with no Assignment for this student's classroom
        // must not appear in their assignment list.
        $unassignedQuestion = Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'An unassigned homework question.',
            'kssm_code' => '4.2',
        ]);
        $otherClassroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'Another Physics Class',
            'join_code' => 'PHYS02',
        ]);
        $otherAssignment = Assignment::create([
            'classroom_id' => $otherClassroom->id,
            'title' => 'Homework 1',
            'due_at' => now()->addWeek(),
        ]);
        $otherAssignment->questions()->attach($unassignedQuestion->id, ['order' => 1]);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->component('SubmitHomework')
            ->where('assignments', [
                [
                    'id' => $assignment->id,
                    'title' => 'Homework 1',
                    'due_at' => $assignment->due_at->toJSON(),
                    'question_id' => $question->id,
                    'question_body' => $question->body,
                    'kssm_code' => '4.1',
                    'skill_name' => 'Thermal Equilibrium',
                    'module_name' => null,
                ],
            ])
            ->has('pastAttempts', 1)
            ->where('pastAttempts.0.question_id', $question->id)
            ->where('pastAttempts.0.question_body', $question->body)
            ->where('pastAttempts.0.kssm_code', '4.1')
            ->where('pastAttempts.0.is_correct', false)
            ->where('pastAttempts.0.diagnoses', [
                ['dimension' => 'conceptual', 'tag' => 'metal_inherently_colder', 'evidence' => 'Metal is naturally colder than wood.'],
            ])
        );
    }

    public function test_a_student_with_no_classroom_sees_no_assignments(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get('/student/homework/submit');

        $response->assertInertia(fn ($page) => $page
            ->component('SubmitHomework')
            ->where('assignments', [])
            ->where('pastAttempts', [])
        );
    }
}
