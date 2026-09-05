import { Head, useForm } from '@inertiajs/react';
import Layout from '../Layout';

const DIMENSION_LABELS = {
    conceptual: 'Misconception',
    curriculum: 'KSSM standard not mastered',
    method: 'Answering method',
    reasoning: 'Reasoning',
};

export default function SubmissionReview({ submission, attempts }) {
    const { data, setData, post, processing } = useForm({
        scores: attempts.map((attempt) => ({
            attempt_id: attempt.id,
            teacher_score: attempt.teacher_score ?? attempt.score ?? 0,
        })),
    });

    function scoreFor(attemptId) {
        return data.scores.find((row) => row.attempt_id === attemptId)?.teacher_score ?? 0;
    }

    function setScore(attemptId, value) {
        setData(
            'scores',
            data.scores.map((row) => (row.attempt_id === attemptId ? { ...row, teacher_score: value } : row)),
        );
    }

    function submit(event) {
        event.preventDefault();
        post(`/teacher/submissions/${submission.id}/validate`, { preserveScroll: true });
    }

    return (
        <Layout>
            <Head title={`Review — ${submission.assignment_title}`} />

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-semibold">{submission.assignment_title}</h1>
                    <p className="text-sm text-slate-500">{submission.student_name}</p>
                </div>
                {submission.teacher_validated_at && (
                    <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                        Validated {new Date(submission.teacher_validated_at).toLocaleString()}
                    </span>
                )}
            </div>

            <form onSubmit={submit} className="mt-6 space-y-4">
                {attempts.map((attempt) => (
                    <div key={attempt.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex items-start justify-between gap-4">
                            <p className="text-sm font-medium text-slate-800">{attempt.question.body}</p>
                            <div className="flex shrink-0 items-center gap-2">
                                {attempt.needs_review && (
                                    <span className="rounded-md bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-800">
                                        Needs review
                                    </span>
                                )}
                                {attempt.sampled_for_audit && (
                                    <span className="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">
                                        Spot-check
                                    </span>
                                )}
                                <span
                                    className={`rounded-md px-2 py-1 text-[10px] font-bold ${attempt.is_correct ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}`}
                                >
                                    {attempt.is_correct ? 'Correct' : 'Incorrect'}
                                </span>
                            </div>
                        </div>

                        <p className="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{attempt.raw_answer}</p>

                        {attempt.diagnoses.length > 0 && (
                            <div className="mt-3 space-y-1.5 text-xs text-slate-600">
                                {attempt.diagnoses.map((diagnosis, index) => (
                                    <p key={index}>
                                        <span className="font-semibold text-slate-800">
                                            {DIMENSION_LABELS[diagnosis.dimension] ?? diagnosis.dimension}:
                                        </span>{' '}
                                        {diagnosis.tag}
                                        {diagnosis.evidence && ` — "${diagnosis.evidence}"`}
                                    </p>
                                ))}
                            </div>
                        )}

                        <div className="mt-4 flex items-center gap-3">
                            <label className="text-xs font-medium text-slate-500" htmlFor={`score-${attempt.id}`}>
                                Score
                            </label>
                            <input
                                id={`score-${attempt.id}`}
                                type="number"
                                min="0"
                                max={attempt.max_score ?? undefined}
                                value={scoreFor(attempt.id)}
                                onChange={(e) => setScore(attempt.id, Number(e.target.value))}
                                className="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm"
                            />
                            <span className="text-xs text-slate-400">
                                / {attempt.max_score ?? '—'} (AI proposed {attempt.score ?? '—'})
                            </span>
                        </div>
                    </div>
                ))}

                {attempts.length === 0 && <p className="text-sm text-slate-500">No graded questions in this submission.</p>}

                {attempts.length > 0 && (
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                    >
                        {processing ? 'Saving…' : 'Save scores'}
                    </button>
                )}
            </form>
        </Layout>
    );
}
