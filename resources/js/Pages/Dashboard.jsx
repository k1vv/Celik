import { Head, router } from '@inertiajs/react';
import Layout from '../Layout';

function cellColor(score) {
    if (score >= 0.7) return 'bg-emerald-500';
    if (score >= 0.5) return 'bg-amber-400';
    return 'bg-rose-500';
}

const DIMENSION_LABELS = {
    conceptual: 'Misconception',
    curriculum: 'KSSM standard not mastered',
    method: 'Answering method',
    reasoning: 'Reasoning',
};

export default function Dashboard({ classrooms, classroom, heatmap, gaps, gapSummary }) {
    if (!classroom) {
        return (
            <Layout>
                <Head title="Dashboard" />
                <p className="text-slate-600">You don't have any classrooms set up yet.</p>
            </Layout>
        );
    }

    const cellFor = (studentId, skillId) =>
        heatmap.cells.find((c) => c.student_id === studentId && c.skill_id === skillId);

    return (
        <Layout>
            <Head title="Dashboard" />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">{classroom.name}</h1>
                    <p className="text-sm text-slate-500">{classroom.subject}</p>
                </div>
                {classrooms.length > 1 && (
                    <select
                        value={classroom.id}
                        onChange={(e) => router.get('/dashboard', { classroom: e.target.value })}
                        className="rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                    >
                        {classrooms.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                )}
            </div>

            {gaps.length > 0 && (
                <div className="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <h2 className="text-sm font-semibold text-rose-800">Class-wide gaps</h2>
                    <p className="mt-1 text-xs text-rose-700">
                        Skills where 40%+ of the class is still below mastery &mdash; worth a group re-teach.
                    </p>
                    <div className="mt-3 space-y-2">
                        {gaps.map((gap) => (
                            <div key={gap.skill_id} className="flex items-center justify-between text-sm">
                                <span className="text-rose-900">{gap.skill}</span>
                                <span className="text-rose-700">
                                    {gap.struggling_count}/{gap.total} students ({gap.struggling_pct}%)
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {gapSummary?.length > 0 && (
                <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="text-sm font-semibold text-slate-800">Diagnosed gaps</h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Not just who's struggling — why. Each row is a distinct misconception or missing standard,
                        and how many students share it.
                    </p>
                    <div className="mt-3 space-y-2">
                        {gapSummary.map((row) => (
                            <div
                                key={`${row.dimension}-${row.tag}`}
                                className="flex items-center justify-between text-sm"
                            >
                                <span className="text-slate-700">
                                    <span className="mr-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">
                                        {DIMENSION_LABELS[row.dimension] ?? row.dimension}
                                    </span>
                                    {row.tag}
                                </span>
                                <span className="font-medium text-slate-700">
                                    {row.student_count} student{row.student_count === 1 ? '' : 's'}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th className="sticky left-0 bg-white p-3 text-left font-medium text-slate-600">
                                Student
                            </th>
                            {heatmap.skills.map((skill) => (
                                <th key={skill.id} className="p-3 text-left font-medium text-slate-600">
                                    {skill.name}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {heatmap.students.map((student) => (
                            <tr key={student.id} className="border-t border-slate-100">
                                <td className="sticky left-0 bg-white p-3 font-medium text-slate-800">
                                    {student.name}
                                </td>
                                {heatmap.skills.map((skill) => {
                                    const cell = cellFor(student.id, skill.id);
                                    const score = cell?.mastery_score ?? 0;

                                    return (
                                        <td key={skill.id} className="p-3">
                                            <div
                                                className={`flex h-9 w-16 items-center justify-center rounded-md text-xs font-semibold text-white ${cellColor(score)}`}
                                                title={`${student.name} — ${skill.name}: ${Math.round(score * 100)}%`}
                                            >
                                                {Math.round(score * 100)}%
                                            </div>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Layout>
    );
}
