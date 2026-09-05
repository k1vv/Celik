<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_another_teachers_classroom_by_id_falls_back_to_the_teachers_own_classroom(): void
    {
        $subject = Subject::create(['name' => 'Physics']);

        $teacher = User::factory()->create(['role' => 'teacher']);
        $ownClassroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'My Class',
            'join_code' => 'MINE01',
        ]);

        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherClassroom = Classroom::create([
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $subject->id,
            'name' => "Someone Else's Class",
            'join_code' => 'THEIR1',
        ]);

        $response = $this->actingAs($teacher)->get("/dashboard?classroom={$otherClassroom->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('classroom.id', $ownClassroom->id)
            ->where('classroom.name', 'My Class')
        );
    }
}
