<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Module;
use App\Models\Question;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The first real Classroom for the Physics content authored by
 * TopicGroundingSeeder/HomeworkQuestionSeeder - none existed before this
 * (the only seeded Classroom anywhere was LearningDataSeeder's Math class).
 * Also seeds the "Heat" curriculum Module and "Homework 1", a real
 * Assignment releasing the seeded 4.1 question to this classroom.
 *
 * Idempotent (firstOrCreate/updateOrCreate) like TopicGroundingSeeder and
 * HomeworkQuestionSeeder, which this depends on - safe to re-run.
 *
 * Deliberately seeds a *dedicated* Physics student rather than reusing
 * LearningDataSeeder's Math cohort: StudentDashboardController's
 * classroomFor() only ever returns a student's first classroom, so a
 * student in two classrooms could see one classroom on the dashboard banner
 * and a different one's assignments below it. A dedicated single-classroom
 * student avoids that entirely.
 */
class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $subject = Subject::where('name', 'Physics')->firstOrFail();

        // A dedicated teacher rather than reusing LearningDataSeeder's Math
        // teacher - keeps this seeder self-contained (doesn't require
        // LearningDataSeeder to have run first) and avoids one teacher
        // implausibly teaching both Math and Physics in the demo data.
        $teacher = User::firstOrCreate(
            ['email' => 'physics.teacher@example.com'],
            ['name' => 'Cikgu Aiman', 'password' => 'password', 'role' => 'teacher'],
        );

        $classroom = Classroom::firstOrCreate(
            ['subject_id' => $subject->id, 'name' => 'Form 4 Physics'],
            ['teacher_id' => $teacher->id, 'join_code' => strtoupper(Str::random(6))],
        );

        $student = User::firstOrCreate(
            ['email' => 'physics.student1@example.com'],
            ['name' => 'Ain Nadia', 'password' => 'password', 'role' => 'student'],
        );
        $classroom->students()->syncWithoutDetaching($student);

        $heat = Module::firstOrCreate(
            ['subject_id' => $subject->id, 'name' => 'Heat'],
            ['order' => 1],
        );
        Skill::whereIn('name', ['Thermal Equilibrium', 'Specific Heat Capacity'])
            ->update(['module_id' => $heat->id]);

        $spoonQuestion = Question::where('kssm_code', '4.1')->where('type', 'homework')->firstOrFail();

        $assignment = Assignment::firstOrCreate(
            ['classroom_id' => $classroom->id, 'title' => 'Homework 1'],
            ['due_at' => now()->addWeek()],
        );
        $assignment->questions()->syncWithoutDetaching([$spoonQuestion->id => ['order' => 1]]);
    }
}
