<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(LearningDataSeeder::class);
        $this->call(TopicGroundingSeeder::class);
        $this->call(HomeworkQuestionSeeder::class);
        $this->call(PhysicsQuizQuestionSeeder::class);
        $this->call(AssignmentSeeder::class);
        $this->call(PracticeSetSeeder::class);
    }
}
