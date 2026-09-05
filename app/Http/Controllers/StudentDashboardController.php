<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('StudentDashboard', [
            'classroom' => $this->classroomFor($request),
        ]);
    }

    public function submitHomework(Request $request): Response
    {
        return Inertia::render('SubmitHomework', [
            'classroom' => $this->classroomFor($request),
        ]);
    }

    /** @return array{id: int, name: string, subject: string}|null */
    private function classroomFor(Request $request): ?array
    {
        $classroom = $request->user()->classrooms()->with('subject')->first();

        if (! $classroom) {
            return null;
        }

        return [
            'id' => $classroom->id,
            'name' => $classroom->name,
            'subject' => $classroom->subject->name,
        ];
    }
}
