<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\TopicGrounding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeworkSubmissionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(): Question
    {
        $subject = Subject::create(['name' => 'Physics']);
        $skill = Skill::create(['subject_id' => $subject->id, 'name' => 'Heat Conduction']);

        TopicGrounding::create([
            'kssm_code' => '9.2.2',
            'syllabus_excerpt' => 'Heat is the transfer of thermal energy from a hotter to a cooler object.',
            'rubric' => 'Full marks require distinguishing heat from temperature.',
            'tag_vocabulary' => ['conceptual' => ['metal_inherently_colder']],
        ]);

        return Question::create([
            'skill_id' => $skill->id,
            'type' => 'homework',
            'body' => 'Explain why a metal spoon feels colder than a wooden spoon left on the same table overnight.',
            'kssm_code' => '9.2.2',
        ]);
    }

    /** Releases $question to $student's classroom, per QuestionPolicy::submitHomework. */
    private function assignToStudent(Question $question, User $student): void
    {
        $classroom = Classroom::create([
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'subject_id' => $question->skill->subject_id,
            'name' => 'Form 4 Physics',
            'join_code' => 'TEST01',
        ]);
        $classroom->students()->attach($student);
        $assignment = Assignment::create(['classroom_id' => $classroom->id, 'title' => 'Homework 1']);
        $assignment->questions()->attach($question->id, ['order' => 1]);
    }

    private function fakeDiagnosis(array $args): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [
                        ['functionCall' => ['name' => 'record_diagnosis', 'args' => $args]],
                    ]]],
                ],
            ], 200),
        ]);
    }

    public function test_a_homework_submission_flashes_the_full_diagnosis_for_the_page_to_render(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $question = $this->makeQuestion();
        $this->assignToStudent($question, $student);

        $this->fakeDiagnosis([
            'correct' => false,
            'conceptual_tag' => 'metal_inherently_colder',
            'conceptual_evidence' => 'Metal is just colder than wood.',
            'confidence' => 0.82,
        ]);

        $response = $this->actingAs($student)->post('/homework', [
            'question_id' => $question->id,
            'answer' => 'Metal is just colder than wood.',
        ]);

        $response->assertSessionHas('result', 'incorrect');
        $response->assertSessionHas('homeworkDiagnosis', function (array $diagnosis) {
            return $diagnosis['correct'] === false
                && $diagnosis['confidence'] === 0.82
                && $diagnosis['conceptual']['tag'] === 'metal_inherently_colder'
                && $diagnosis['conceptual']['evidence'] === 'Metal is just colder than wood.';
        });
    }
}
