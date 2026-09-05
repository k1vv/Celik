import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import StudentDashboard from '../Components/Student/StudentDashboard';
import { EmptyState, Icon, ProgressBar } from '../Components/Student/DashboardComponents';

// TODO BACKEND: Replace with teacher-assigned practice sets and their questions.
const practiceSets = [
    { id: 'heat', title: 'Heat Transfer Foundations', subject: 'Science', teacher: 'Cikgu Rahimah', initials: 'CR', description: 'Strengthen your understanding of conduction and heat transfer.', questionCount: 5, estimatedMinutes: 10, difficulty: 'Foundation', progress: 0, status: 'assigned', dueDate: '8 Sep', gaps: [{ code: 'S-04', label: 'Heat conduction' }, { code: 'A-01', label: 'Explaining your reasoning' }] },
    { id: 'explanations', title: 'Explaining Scientific Answers', subject: 'Science', teacher: 'Cikgu Rahimah', initials: 'CR', description: 'Practice explaining why an answer happens, not only stating the result.', questionCount: 4, estimatedMinutes: 8, difficulty: 'Core', progress: 50, status: 'in_progress', dueDate: null, gaps: [{ code: 'A-01', label: 'Explaining your reasoning' }] },
];

const localQuestions = [
    { id: 'local-2', type: 'multiple_choice', topic: 'Heat Transfer', prompt: 'Which material conducts heat away from your hand faster?', options: ['Metal', 'Wood', 'Both at the same rate', 'Neither'] },
    { id: 'local-3', type: 'explanation', topic: 'Heat Transfer', prompt: 'Explain why a metal spoon can feel colder than a wooden spoon in the same room.' },
    { id: 'local-4', type: 'multiple_choice', topic: 'Heat Transfer', prompt: 'What moves from your hand into the metal spoon?', options: ['Heat energy', 'Coldness', 'Air', 'Light'] },
    { id: 'local-5', type: 'short_answer', topic: 'Scientific explanations', prompt: 'Name the heat-transfer process involved when heat moves through a metal spoon.' },
    { id: 'local-6', type: 'multiple_choice', topic: 'Heat Transfer', prompt: 'Which sentence best explains why metal feels colder?', options: ['Metal takes heat from your hand faster', 'Metal is naturally colder', 'Wood makes heat', 'Both materials cool the room'] },
];

const primaryAction = 'auth-focus inline-flex items-center justify-center gap-2 rounded-xl bg-auth-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-auth-green/90';
const setStyle = { assigned: 'border-[#eadf91] bg-linear-to-br from-[#fff3b0] to-auth-soft', in_progress: 'border-[#d7e2f2] bg-[#f8fbff]' };

export default function Practice(props) {
    return <StudentDashboard classroom={props.classroom} initialView="practice"><PracticePage {...props} /></StudentDashboard>;
}

export function PracticePage({ classroom, question, masteries = [] }) {
    const [view, setView] = useState('dashboard');
    const [selectedSet, setSelectedSet] = useState(null);
    const [questionIndex, setQuestionIndex] = useState(0);
    const [answers, setAnswers] = useState({});
    const [confirming, setConfirming] = useState(false);
    const { flash } = usePage().props;
    const { post, processing } = useForm();
    const questions = useMemo(() => buildQuestions(question), [question]);

    function openSet(practiceSet) {
        setSelectedSet(practiceSet);
        setQuestionIndex(0);
        setAnswers({});
        setView('intro');
    }

    function submitAdaptiveAnswer() {
        const adaptive = questions.find((item) => item.source === 'adaptive');
        if (!adaptive || !answers[adaptive.id]) {
            setConfirming(false);
            return;
        }
        post('/practice', { preserveScroll: true, data: { question_id: adaptive.id, selected_option: answers[adaptive.id] } });
    }

    if (!classroom) return <><Head title="Practice" /><EmptyState title="You're not enrolled in a class yet." description="Ask your teacher for a join code to see assigned practice sets." /></>;
    if (view === 'dashboard') return <PracticeDashboard masteries={masteries} onOpen={openSet} />;
    if (view === 'intro') return <PracticeIntro practiceSet={selectedSet} onBack={() => setView('dashboard')} onStart={() => setView('session')} />;
    if (view === 'review') return <PracticeReview questions={questions} answers={answers} onBack={() => setView('session')} onEdit={(index) => { setQuestionIndex(index); setView('session'); }} onSubmit={() => setConfirming(true)} canSubmit={Boolean(questions.find((item) => item.source === 'adaptive' && answers[item.id]))} />;

    return <><PracticeSession classroom={classroom} practiceSet={selectedSet} questions={questions} questionIndex={questionIndex} answers={answers} onAnswer={(value) => setAnswers((current) => ({ ...current, [questions[questionIndex].id]: value }))} onExit={() => setView('dashboard')} onPrevious={() => setQuestionIndex((index) => Math.max(0, index - 1))} onNext={() => questionIndex === questions.length - 1 ? setView('review') : setQuestionIndex((index) => index + 1)} onNavigate={setQuestionIndex} />{flash?.result && <div className="fixed bottom-5 right-5 max-w-sm rounded-2xl border border-auth-green/15 bg-white p-4 text-sm shadow-lg"><p className="font-semibold text-auth-green">Adaptive answer recorded</p><p className="mt-1 text-auth-muted">{flash.result === 'correct' ? 'Nice work — your mastery has been updated.' : 'Your response was recorded and your practice will adapt.'}</p></div>}{confirming && <ConfirmationModal processing={processing} onCancel={() => setConfirming(false)} onConfirm={submitAdaptiveAnswer} />}</>;
}

function PracticeDashboard({ masteries, onOpen }) {
    return <div className="grid gap-7"><Head title="Practice" /><div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">YOUR LEARNING SPACE</p><h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Practice</h1><p className="mt-3 max-w-2xl text-sm leading-6 text-auth-muted">Practice sets recommended by your teachers and built around what you’re learning.</p></div><dl className="flex gap-3"><StatCard value="2" label="To complete" /><StatCard value="1" label="Completed this week" /></dl></div><SetSection eyebrow="RECOMMENDED BY YOUR TEACHER" title="Start with what matters most" description="Practice selected for you based on your recent work." sets={practiceSets.filter((item) => item.status === 'assigned')} onOpen={onOpen} /><SetSection eyebrow="CONTINUE PRACTICING" title="Pick up where you left off" sets={practiceSets.filter((item) => item.status === 'in_progress')} onOpen={onOpen} /><MasteryCard masteries={masteries} /></div>;
}

function StatCard({ value, label }) {
    return <div className="min-w-26 rounded-2xl border border-auth-border bg-white px-4 py-3 shadow-xs"><dd className="text-2xl font-semibold tracking-tight text-auth-green">{value}</dd><dt className="mt-1 text-[11px] text-auth-muted">{label}</dt></div>;
}

function SetSection({ eyebrow, title, description, sets, onOpen }) {
    return <section><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{eyebrow}</p><h2 className="mt-2 text-2xl font-semibold tracking-tight">{title}</h2>{description && <p className="mt-2 text-sm text-auth-muted">{description}</p>}<div className="mt-4 grid gap-4 xl:grid-cols-2">{sets.map((item) => <PracticeSetCard key={item.id} practiceSet={item} onOpen={() => onOpen(item)} />)}</div></section>;
}

export function PracticeSetCard({ practiceSet, onOpen }) {
    const completed = Math.round(practiceSet.questionCount * practiceSet.progress / 100);
    return <article className={'rounded-[1.6rem] border p-5 shadow-xs ' + (setStyle[practiceSet.status] || setStyle.assigned)}><div className="flex flex-wrap items-start justify-between gap-3"><span className="rounded-full bg-white/70 px-3 py-1.5 text-[11px] font-bold text-auth-green">{practiceSet.subject}</span><span className="inline-flex items-center gap-2 text-xs font-medium text-auth-muted"><span className="flex size-6 items-center justify-center rounded-full bg-white/80 text-[10px] font-bold text-auth-green">{practiceSet.initials}</span>Recommended by {practiceSet.teacher}</span></div><h3 className="mt-5 text-xl font-semibold tracking-tight">{practiceSet.title}</h3><p className="mt-2 text-sm leading-6 text-auth-muted">{practiceSet.description}</p><div className="mt-5 flex flex-wrap gap-2 text-xs"><span className="rounded-full bg-white/65 px-3 py-1.5">{practiceSet.questionCount} questions</span><span className="rounded-full bg-white/65 px-3 py-1.5">~{practiceSet.estimatedMinutes} min</span><span className="rounded-full bg-white/65 px-3 py-1.5">{practiceSet.difficulty}</span>{practiceSet.dueDate && <span className="rounded-full bg-white/65 px-3 py-1.5">Due {practiceSet.dueDate}</span>}</div><p className="mt-5 text-xs font-medium text-auth-muted">Built from your recent gaps</p><div className="mt-2 flex gap-2">{practiceSet.gaps.map((gap) => <span key={gap.code} className="rounded-md border border-auth-green/15 bg-white/60 px-2 py-1 text-[11px] font-bold text-auth-green">{gap.code}</span>)}</div>{practiceSet.status === 'in_progress' && <div className="mt-5"><div className="mb-2 flex justify-between text-xs text-auth-muted"><span>{completed} of {practiceSet.questionCount} completed</span><span>{practiceSet.progress}%</span></div><ProgressBar completed={practiceSet.progress} total={100} label={practiceSet.title + ' progress'} /></div>}<button type="button" onClick={onOpen} className={'mt-6 ' + primaryAction}>{practiceSet.status === 'in_progress' ? 'Continue' : 'Start practice'} <Icon className="size-4" /></button></article>;
}

function PracticeIntro({ practiceSet, onBack, onStart }) {
    return <div className="mx-auto max-w-3xl"><button type="button" onClick={onBack} className="auth-focus inline-flex items-center gap-2 text-sm font-semibold text-auth-green">← Back to practice</button><section className="mt-5 rounded-[1.75rem] border border-auth-green/10 bg-linear-to-br from-[#fff3b0] to-auth-soft p-6 shadow-xs sm:p-8"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{practiceSet.subject.toUpperCase()}</p><h1 className="mt-3 text-3xl font-semibold tracking-tight">{practiceSet.title}</h1><p className="mt-3 text-sm font-medium text-auth-green">Recommended by {practiceSet.teacher}</p><p className="mt-5 text-sm leading-6 text-auth-muted">Let’s strengthen the ideas that appeared in your recent marked work.</p><div className="mt-6 flex flex-wrap gap-2"><span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.questionCount} questions</span><span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.estimatedMinutes} min</span><span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.difficulty}</span></div><div className="mt-8 border-t border-auth-green/10 pt-6"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">FOCUS AREAS</p><div className="mt-4 grid gap-3 sm:grid-cols-2">{practiceSet.gaps.map((gap) => <div key={gap.code} className="rounded-xl bg-white/65 p-4"><p className="text-xs font-bold text-auth-green">{gap.code}</p><p className="mt-1 text-sm font-medium">{gap.label}</p></div>)}</div></div><button type="button" onClick={onStart} className={'mt-8 ' + primaryAction}>Start set <Icon className="size-4" /></button></section></div>;
}

function PracticeSession({ classroom, practiceSet, questions, questionIndex, answers, onAnswer, onExit, onPrevious, onNext, onNavigate }) {
    const item = questions[questionIndex];
    const answered = Object.keys(answers).length;
    return <div><button type="button" onClick={onExit} className="auth-focus inline-flex items-center gap-2 text-sm font-semibold text-auth-green">← Exit practice</button><div className="mt-4 flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-auth-muted">{classroom.subject} · {practiceSet.title}</p><span className="rounded-full bg-auth-soft px-3 py-1.5 text-xs font-semibold text-auth-green">Question {questionIndex + 1} of {questions.length}</span></div><ProgressBar completed={answered} total={questions.length} label="Practice set progress" /><div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.9fr)_minmax(17rem,.75fr)]"><section className="rounded-[1.75rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{item.topic.toUpperCase()} · {practiceSet.difficulty.toUpperCase()}</p><h1 className="mt-4 text-xl font-semibold leading-8 tracking-tight sm:text-2xl">{item.prompt}</h1><QuestionInput item={item} value={answers[item.id] || ''} onChange={onAnswer} /><div className="mt-8 flex items-center justify-between gap-3"><button type="button" disabled={questionIndex === 0} onClick={onPrevious} className="auth-focus rounded-xl border border-auth-border px-4 py-2.5 text-sm font-semibold text-auth-green disabled:cursor-not-allowed disabled:opacity-40">← Previous</button><button type="button" onClick={onNext} className={primaryAction}>{questionIndex === questions.length - 1 ? 'Review answers' : 'Save & next'} <Icon className="size-4" /></button></div></section><SetProgressCard practiceSet={practiceSet} questions={questions} answers={answers} current={questionIndex} onNavigate={onNavigate} /></div></div>;
}

function QuestionInput({ item, value, onChange }) {
    if (item.type === 'explanation') return <label className="mt-6 block text-sm font-semibold">Explain your answer<textarea value={value} onChange={(event) => onChange(event.target.value)} className="auth-focus mt-3 min-h-36 w-full rounded-2xl border border-auth-border bg-[#fcfcf9] p-4 text-sm font-normal" placeholder="Try to explain why, not just what happens." /></label>;
    if (item.type === 'short_answer') return <label className="mt-6 block text-sm font-semibold">Your answer<input value={value} onChange={(event) => onChange(event.target.value)} className="auth-focus mt-3 w-full rounded-xl border border-auth-border bg-[#fcfcf9] px-4 py-3 text-sm font-normal" /></label>;
    return <div className="mt-6 grid gap-3 sm:grid-cols-2">{item.options.map((option, index) => <button key={option} type="button" onClick={() => onChange(option)} className={'auth-focus flex min-h-15 items-center gap-3 rounded-2xl border p-4 text-left text-sm font-semibold transition-colors ' + (value === option ? 'border-auth-green bg-auth-soft text-auth-green' : 'border-auth-border bg-white hover:border-auth-green/35')}><span className={'flex size-7 shrink-0 items-center justify-center rounded-full text-xs ' + (value === option ? 'bg-auth-green text-white' : 'bg-auth-page text-auth-muted')}>{value === option ? '✓' : String.fromCharCode(65 + index)}</span>{option}</button>)}</div>;
}

function SetProgressCard({ practiceSet, questions, answers, current, onNavigate }) {
    const answered = Object.keys(answers).length;
    return <aside className="rounded-[1.5rem] border border-auth-border bg-white p-5 shadow-xs"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">SET PROGRESS</p><h2 className="mt-2 text-lg font-semibold">{practiceSet.title}</h2><p className="mt-3 text-sm text-auth-muted">{answered} / {questions.length} answered</p><ProgressBar completed={answered} total={questions.length} label="Answered questions" /><div className="mt-6 grid grid-cols-5 gap-2">{questions.map((item, index) => <button key={item.id} type="button" onClick={() => onNavigate(index)} aria-label={'Question ' + (index + 1) + (answers[item.id] ? ', answered' : '')} className={'auth-focus aspect-square rounded-xl text-sm font-semibold ' + (index === current ? 'bg-auth-green text-white' : answers[item.id] ? 'bg-auth-soft text-auth-green' : 'bg-auth-page text-auth-muted')}>{answers[item.id] ? '✓' : index + 1}</button>)}</div><div className="mt-7 border-t border-auth-border pt-5"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">FOCUS</p><div className="mt-3 space-y-3">{practiceSet.gaps.map((gap) => <p key={gap.code} className="text-sm"><span className="mr-2 rounded-md bg-auth-soft px-2 py-1 text-xs font-bold text-auth-green">{gap.code}</span>{gap.label}</p>)}</div><p className="mt-5 text-sm text-auth-muted">Teacher: <span className="font-medium text-auth-ink">{practiceSet.teacher}</span></p></div></aside>;
}

function PracticeReview({ questions, answers, onBack, onEdit, onSubmit, canSubmit }) {
    const answered = Object.keys(answers).length;
    return <div className="mx-auto max-w-4xl"><button type="button" onClick={onBack} className="auth-focus inline-flex items-center gap-2 text-sm font-semibold text-auth-green">← Back to questions</button><section className="mt-5 rounded-[1.75rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">REVIEW ANSWERS</p><h1 className="mt-3 text-3xl font-semibold tracking-tight">Review your answers</h1><p className="mt-3 text-sm leading-6 text-auth-muted">Make sure you’re happy with your answers before submitting.</p><div className="mt-6 flex flex-wrap gap-3"><span className="rounded-full bg-auth-page px-3 py-1.5 text-sm">{questions.length} questions</span><span className="rounded-full bg-auth-soft px-3 py-1.5 text-sm text-auth-green">{answered} answered</span><span className="rounded-full bg-[#fff3b0] px-3 py-1.5 text-sm">{questions.length - answered} unanswered</span></div>{answered < questions.length && <p className="mt-5 rounded-xl bg-[#fff8d5] p-4 text-sm text-[#806d16]">You still have {questions.length - answered} question{questions.length - answered === 1 ? '' : 's'} unanswered.</p>}<div className="mt-6 divide-y divide-auth-border">{questions.map((item, index) => <div key={item.id} className="flex items-center justify-between gap-4 py-4"><div className="min-w-0"><p className="text-sm font-semibold">Question {index + 1}</p><p className="mt-1 truncate text-sm text-auth-muted">{answers[item.id] || 'Not answered'}</p></div><button type="button" onClick={() => onEdit(index)} className="auth-focus text-sm font-semibold text-auth-green underline underline-offset-4">{answers[item.id] ? 'Edit' : 'Answer question'}</button></div>)}</div>{/* TODO BACKEND: Persist every practice-set response and completion before enabling set-level submission. */}<div className="mt-7 rounded-2xl border border-[#e7eef9] bg-[#f7faff] p-4 text-sm text-auth-muted"><p className="font-semibold text-auth-ink">Practice-set submission is being prepared</p><p className="mt-1">Your current adaptive answer can still be recorded. Saving the complete teacher-assigned set requires backend practice-set support.</p></div><button type="button" disabled={!canSubmit} onClick={onSubmit} className={'mt-5 ' + primaryAction + ' disabled:cursor-not-allowed disabled:bg-auth-green/35'}>Submit adaptive answer <Icon className="size-4" /></button></section></div>;
}

function ConfirmationModal({ processing, onCancel, onConfirm }) {
    return <div role="dialog" aria-modal="true" aria-labelledby="submit-practice-title" className="fixed inset-0 z-50 grid place-items-center bg-auth-ink/30 p-4"><div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl"><h2 id="submit-practice-title" className="text-xl font-semibold">Submit this adaptive answer?</h2><p className="mt-3 text-sm leading-6 text-auth-muted">Your response will update your adaptive practice history. Teacher-assigned set submission will be available when the backend supports it.</p><div className="mt-6 flex justify-end gap-3"><button type="button" onClick={onCancel} className="auth-focus rounded-xl px-4 py-2.5 text-sm font-semibold text-auth-muted">Cancel</button><button type="button" disabled={processing} onClick={onConfirm} className="auth-focus rounded-xl bg-auth-green px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Submit answer</button></div></div></div>;
}

function MasteryCard({ masteries }) {
    return <section className="rounded-[1.5rem] border border-auth-border bg-white p-5 shadow-xs sm:p-6"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">YOUR MASTERY</p><h2 className="mt-2 text-xl font-semibold tracking-tight">Keep building confidence</h2>{masteries.length ? <div className="mt-5 grid gap-4 sm:grid-cols-2">{masteries.map((item) => <div key={item.skill}><div className="flex justify-between text-xs text-auth-muted"><span>{item.skill}</span><span>{Math.round(item.mastery_score * 100)}%</span></div><ProgressBar completed={item.mastery_score * 100} total={100} label={item.skill + ' mastery'} /></div>)}</div> : <p className="mt-4 text-sm text-auth-muted">Your mastery will appear after your first adaptive answer.</p>}</section>;
}

function buildQuestions(question) {
    const adaptive = question ? [{ id: question.id, source: 'adaptive', type: 'multiple_choice', topic: question.skill, prompt: question.body, options: question.options }] : [];
    return [...adaptive, ...localQuestions].slice(0, 5);
}
