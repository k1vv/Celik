import { Head, useForm, usePage } from '@inertiajs/react';
import Layout from '../Layout';

function MasteryBar({ skill, mastery_score }) {
    const pct = Math.round(mastery_score * 100);
    const color = mastery_score >= 0.7 ? 'bg-emerald-500' : mastery_score >= 0.5 ? 'bg-amber-500' : 'bg-rose-500';

    return (
        <div>
            <div className="flex justify-between text-xs text-slate-600">
                <span>{skill}</span>
                <span>{pct}%</span>
            </div>
            <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

export default function Practice({ classroom, question, masteries }) {
    const { flash } = usePage().props;
    const { data, setData, post, processing } = useForm({
        question_id: question?.id ?? null,
        selected_option: '',
    });

    const answer = (option) => {
        setData('selected_option', option);
        post('/practice', {
            preserveScroll: true,
            data: { question_id: question.id, selected_option: option },
        });
    };

    if (!classroom) {
        return (
            <Layout>
                <Head title="Practice" />
                <p className="text-slate-600">You're not enrolled in a class yet. Ask your teacher for a join code.</p>
            </Layout>
        );
    }

    return (
        <Layout>
            <Head title="Practice" />
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <p className="text-sm text-slate-500">
                        {classroom.subject} &middot; {classroom.name}
                    </p>

                    {flash?.result && (
                        <div
                            className={`mt-3 rounded-md px-4 py-2 text-sm ${
                                flash.result === 'correct'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-rose-50 text-rose-700'
                            }`}
                        >
                            {flash.result === 'correct'
                                ? 'Correct! Nice work.'
                                : `Not quite — the correct answer was ${flash.correct_option}.`}
                        </div>
                    )}

                    {question ? (
                        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <p className="text-xs font-medium uppercase tracking-wide text-indigo-600">
                                {question.skill} &middot; difficulty {question.difficulty}/5
                            </p>
                            <p className="mt-3 text-lg font-medium">{question.body}</p>

                            <div className="mt-5 grid grid-cols-2 gap-3">
                                {question.options.map((option) => (
                                    <button
                                        key={option}
                                        disabled={processing}
                                        onClick={() => answer(option)}
                                        className="rounded-md border border-slate-300 px-4 py-3 text-sm font-medium hover:border-indigo-400 hover:bg-indigo-50 disabled:opacity-50"
                                    >
                                        {option}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <p className="mt-6 text-slate-600">No questions available yet for this class.</p>
                    )}
                </div>

                <div>
                    <h2 className="text-sm font-semibold text-slate-700">Your mastery</h2>
                    <div className="mt-3 space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        {masteries.length === 0 && <p className="text-sm text-slate-500">No attempts yet.</p>}
                        {masteries.map((m) => (
                            <MasteryBar key={m.skill} {...m} />
                        ))}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
