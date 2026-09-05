<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_pdf_for_an_assignment_not_released_to_the_students_classroom_returns_403(): void
    {
        Storage::fake('local');

        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Thermal Equilibrium']);
        $question = Question::create(['skill_id' => $skill->id, 'type' => 'homework', 'body' => 'Q1', 'kssm_code' => '4.1']);
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $subject->id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);

        // Not enrolled in $classroom - nothing ties this student to the assignment.
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->post('/submissions', [
            'assignment_id' => $assignment->id,
            'pdf' => UploadedFile::fake()->create('answers.pdf', 10, 'application/pdf'),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('submissions', ['assignment_id' => $assignment->id]);
    }
}
