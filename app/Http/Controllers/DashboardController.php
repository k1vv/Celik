<?php

namespace App\Http\Controllers;

use App\Models\Mastery;
use App\Services\AdaptivePracticeService;
use App\Services\GapSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private GapSummaryService $gapSummary) {}

    public function index(Request $request): Response
    {
        $teacher = $request->user();
        $classrooms = $teacher->classroomsTeaching()->with('subject')->get();

        $selectedId = (int) $request->integer('classroom', $classrooms->first()?->id ?? 0);
        $classroom = $classrooms->firstWhere('id', $selectedId) ?? $classrooms->first();

        if (! $classroom) {
            return Inertia::render('Dashboard', [
                'classrooms' => [],
                'classroom' => null,
                'heatmap' => ['students' => [], 'skills' => [], 'cells' => []],
                'gaps' => [],
                'gapSummary' => [],
            ]);
        }

        $classroom->load('subject.skills', 'students');
        $skills = $classroom->subject->skills;
        $students = $classroom->students;

        $masteries = Mastery::whereIn('user_id', $students->pluck('id'))
            ->whereIn('skill_id', $skills->pluck('id'))
            ->get()
            ->groupBy(fn ($m) => $m->user_id.'-'.$m->skill_id);

        $cells = [];
        $gapCounters = [];

        foreach ($students as $student) {
            foreach ($skills as $skill) {
                $mastery = $masteries->get($student->id.'-'.$skill->id)?->first();
                $score = $mastery?->mastery_score ?? 0.3;

                $cells[] = [
                    'student_id' => $student->id,
                    'skill_id' => $skill->id,
                    'mastery_score' => round($score, 2),
                ];

                $gapCounters[$skill->id]['total'] = ($gapCounters[$skill->id]['total'] ?? 0) + 1;
                if ($score < AdaptivePracticeService::STRUGGLING_THRESHOLD) {
                    $gapCounters[$skill->id]['struggling'] = ($gapCounters[$skill->id]['struggling'] ?? 0) + 1;
                }
            }
        }

        $gaps = $skills
            ->map(function ($skill) use ($gapCounters) {
                $total = $gapCounters[$skill->id]['total'] ?? 0;
                $struggling = $gapCounters[$skill->id]['struggling'] ?? 0;

                return [
                    'skill_id' => $skill->id,
                    'skill' => $skill->name,
                    'struggling_count' => $struggling,
                    'total' => $total,
                    'struggling_pct' => $total ? round(100 * $struggling / $total) : 0,
                ];
            })
            ->filter(fn ($gap) => $gap['struggling_pct'] >= 40)
            ->sortByDesc('struggling_pct')
            ->values();

        return Inertia::render('Dashboard', [
            'classrooms' => $classrooms->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'classroom' => ['id' => $classroom->id, 'name' => $classroom->name, 'subject' => $classroom->subject->name],
            'heatmap' => [
                'students' => $students->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'skills' => $skills->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'cells' => $cells,
            ],
            'gaps' => $gaps,
            'gapSummary' => $this->gapSummary->forStudents($students->pluck('id')),
        ]);
    }
}
