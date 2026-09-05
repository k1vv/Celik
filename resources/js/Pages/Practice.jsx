import { Head, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import StudentDashboard from '../Components/Student/StudentDashboard';
import { EmptyState, Icon, ProgressBar } from '../Components/Student/DashboardComponents';

const primaryAction = 'auth-focus inline-flex items-center justify-center gap-2 rounded-xl bg-auth-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-auth-green/90';
const setStyle = { assigned: 'border-[#eadf91] bg-linear-to-br from-[#fff3b0] to-auth-soft', in_progress: 'border-[#d7e2f2] bg-[#f8fbff]', completed: 'border-auth-border bg-white' };

export default function Practice(props) {
    return <StudentDashboard classroom={props.classroom} initialView="practice"><PracticePage {...props} /></StudentDashboard>;
}

export function PracticePage({ classroom, question, masteries = [], practiceSets = [] }) {
    const [view, setView] = useState('dashboard');
    const [selectedSet, setSelectedSet] = useState(null);
    const [questionIndex, setQuestionIndex] = useState(0);
    const [answers, setAnswers] = useState({});
    const { flash } = usePage().props;
    const { post, processing } = useForm();

    // Outside a set: the single adaptive next-question AdaptivePracticeService
    // picked. Inside a set: every question that set's teacher bundled into it.
    const questions = useMemo(() => {
        if (selectedSet) return selectedSet.questions.map((item) => ({ id: item.id, source: 'set', topic: item.skill, prompt: item.body, options: item.options }));
        return question ? [{ id: question.id, source: 'adaptive', topic: question.skill, prompt: question.body, options: question.options }] : [];
    }, [selectedSet, question]);

    function openSet(practiceSet) {
        setSelectedSet(practiceSet);
        setQuestionIndex(0);
        setAnswers({});
        setView('intro');
    }

    function submitAnswer() {
        const item = questions[questionIndex];
        if (!item || !answers[item.id]) return;
        post('/practice', {
            preserveScroll: true,
            data: { question_id: item.id, selected_option: answers[item.id], practice_set_id: selectedSet?.id ?? null },
            onSuccess: () => {
                if (questionIndex < questions.length - 1) {
                    setQuestionIndex((index) => index + 1);
                } else {
                    setView('dashboard');
                    setSelectedSet(null);
                }
            },
        });
    }

    function startAdaptive() {
        setSelectedSet(null);
        setQuestionIndex(0);
        setAnswers({});
        setView('session');
    }

    if (!classroom) return <><Head title="Practice" /><EmptyState title="You're not enrolled in a class yet." description="Ask your teacher for a join code to see assigned practice sets." /></>;
    if (view === 'dashboard') return <PracticeDashboard masteries={masteries} practiceSets={practiceSets} question={question} onStartAdaptive={startAdaptive} onOpen={openSet} />;
    if (view === 'intro') return <PracticeIntro practiceSet={selectedSet} onBack={() => setView('dashboard')} onStart={() => setView('session')} />;

    // Each question is graded instantly on submit (per engine.md's synchronous
    // quiz-path design) - so "Save & next" submits immediately rather than
    // batching answers behind a separate review-then-submit step.
    return <><PracticeSession classroom={classroom} practiceSet={selectedSet} questions={questions} questionIndex={questionIndex} answers={answers} processing={processing} onAnswer={(value) => setAnswers((current) => ({ ...current, [questions[questionIndex].id]: value }))} onExit={() => { setView('dashboard'); setSelectedSet(null); }} onPrevious={() => setQuestionIndex((index) => Math.max(0, index - 1))} onNext={submitAnswer} onNavigate={setQuestionIndex} />{flash?.result && <div className="fixed bottom-5 right-5 max-w-sm rounded-2xl border border-auth-green/15 bg-white p-4 text-sm shadow-lg"><p className="font-semibold text-auth-green">Answer recorded</p><p className="mt-1 text-auth-muted">{flash.result === 'correct' ? 'Nice work — your mastery has been updated.' : 'Your response was recorded and your practice will adapt.'}</p></div>}</>;
}

function PracticeDashboard({ masteries, practiceSets, question, onStartAdaptive, onOpen }) {
    const assigned = practiceSets.filter((item) => item.status === 'assigned');
    const inProgress = practiceSets.filter((item) => item.status === 'in_progress');
    return <div className="grid gap-7"><Head title="Practice" /><div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">YOUR LEARNING SPACE</p><h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Practice</h1><p className="mt-3 max-w-2xl text-sm leading-6 text-auth-muted">Practice sets recommended by your teachers and built around what you’re learning.</p></div><dl className="flex gap-3"><StatCard value={assigned.length + inProgress.length} label="To complete" /><StatCard value={practiceSets.filter((item) => item.status === 'completed').length} label="Completed" /></dl></div>{question && <AdaptiveCard question={question} onStart={onStartAdaptive} />}{practiceSets.length === 0 && <EmptyState title="No practice sets have been released yet." description="Your teacher hasn't assigned any practice to your class." />}<SetSection eyebrow="RECOMMENDED BY YOUR TEACHER" title="Start with what matters most" description="Practice selected for you based on your recent work." sets={assigned} onOpen={onOpen} /><SetSection eyebrow="CONTINUE PRACTICING" title="Pick up where you left off" sets={inProgress} onOpen={onOpen} /><MasteryCard masteries={masteries} /></div>;
}

function AdaptiveCard({ question, onStart }) {
    return <section className="rounded-[1.6rem] border border-auth-green/10 bg-linear-to-br from-[#fff3b0] to-auth-soft p-5 shadow-xs sm:p-6"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">PICKED FOR YOU RIGHT NOW</p><h2 className="mt-2 text-xl font-semibold tracking-tight">Your next adaptive question</h2><p className="mt-2 text-sm text-auth-muted">{question.skill} — adjusts to your mastery after every answer.</p><button type="button" onClick={onStart} className={'mt-4 ' + primaryAction}>Answer it now <Icon className="size-4" /></button></section>;
}

function StatCard({ value, label }) {
    return <div className="min-w-26 rounded-2xl border border-auth-border bg-white px-4 py-3 shadow-xs"><dd className="text-2xl font-semibold tracking-tight text-auth-green">{value}</dd><dt className="mt-1 text-[11px] text-auth-muted">{label}</dt></div>;
}

function SetSection({ eyebrow, title, description, sets, onOpen }) {
    if (!sets.length) return null;
    return <section><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{eyebrow}</p><h2 className="mt-2 text-2xl font-semibold tracking-tight">{title}</h2>{description && <p className="mt-2 text-sm text-auth-muted">{description}</p>}<div className="mt-4 grid gap-4 xl:grid-cols-2">{sets.map((item) => <PracticeSetCard key={item.id} practiceSet={item} onOpen={() => onOpen(item)} />)}</div></section>;
}

function initialsOf(name) {
    return name.split(' ').map((word) => word[0]).slice(0, 2).join('').toUpperCase();
}

function GapPills({ gaps }) {
    if (!gaps.length) return null;
    return <div className="flex flex-wrap gap-2">{gaps.map((gap) => <span key={gap.dimension + gap.tag} title={gap.tag} className="rounded-md border border-auth-green/15 bg-white/60 px-2 py-1 text-[11px] font-bold text-auth-green">{gap.dimension.slice(0, 4).toUpperCase()} · {gap.tag}</span>)}</div>;
}

export function PracticeSetCard({ practiceSet, onOpen }) {
    const completed = Math.round(practiceSet.questionCount * practiceSet.progress / 100);
    return <article className={'rounded-[1.6rem] border p-5 shadow-xs ' + (setStyle[practiceSet.status] || setStyle.assigned)}><div className="flex flex-wrap items-start justify-between gap-3"><span className="rounded-full bg-white/70 px-3 py-1.5 text-[11px] font-bold text-auth-green">{practiceSet.subject}</span><span className="inline-flex items-center gap-2 text-xs font-medium text-auth-muted"><span className="flex size-6 items-center justify-center rounded-full bg-white/80 text-[10px] font-bold text-auth-green">{initialsOf(practiceSet.teacher)}</span>Recommended by {practiceSet.teacher}</span></div><h3 className="mt-5 text-xl font-semibold tracking-tight">{practiceSet.title}</h3><p className="mt-2 text-sm leading-6 text-auth-muted">{practiceSet.description}</p><div className="mt-5 flex flex-wrap gap-2 text-xs"><span className="rounded-full bg-white/65 px-3 py-1.5">{practiceSet.questionCount} questions</span><span className="rounded-full bg-white/65 px-3 py-1.5">~{practiceSet.estimatedMinutes} min</span>{practiceSet.difficulty && <span className="rounded-full bg-white/65 px-3 py-1.5">{practiceSet.difficulty}</span>}{practiceSet.dueDate && <span className="rounded-full bg-white/65 px-3 py-1.5">Due {new Date(practiceSet.dueDate).toLocaleDateString()}</span>}</div>{practiceSet.gaps.length > 0 && <><p className="mt-5 text-xs font-medium text-auth-muted">Built from your open gaps on this topic</p><div className="mt-2"><GapPills gaps={practiceSet.gaps} /></div></>}{practiceSet.status === 'in_progress' && <div className="mt-5"><div className="mb-2 flex justify-between text-xs text-auth-muted"><span>{completed} of {practiceSet.questionCount} completed</span><span>{practiceSet.progress}%</span></div><ProgressBar completed={practiceSet.progress} total={100} label={practiceSet.title + ' progress'} /></div>}<button type="button" onClick={onOpen} className={'mt-6 ' + primaryAction}>{practiceSet.status === 'in_progress' ? 'Continue' : 'Start practice'} <Icon className="size-4" /></button></article>;
}

function PracticeIntro({ practiceSet, onBack, onStart }) {
    return <div className="mx-auto max-w-3xl"><button type="button" onClick={onBack} className="auth-focus inline-flex items-center gap-2 text-sm font-semibold text-auth-green">← Back to practice</button><section className="mt-5 rounded-[1.75rem] border border-auth-green/10 bg-linear-to-br from-[#fff3b0] to-auth-soft p-6 shadow-xs sm:p-8"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{practiceSet.subject.toUpperCase()}</p><h1 className="mt-3 text-3xl font-semibold tracking-tight">{practiceSet.title}</h1><p className="mt-3 text-sm font-medium text-auth-green">Recommended by {practiceSet.teacher}</p><p className="mt-5 text-sm leading-6 text-auth-muted">{practiceSet.description || 'Let’s strengthen the ideas that appeared in your recent work.'}</p><div className="mt-6 flex flex-wrap gap-2"><span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.questionCount} questions</span><span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.estimatedMinutes} min</span>{practiceSet.difficulty && <span className="rounded-full bg-white/65 px-3 py-1.5 text-sm">{practiceSet.difficulty}</span>}</div>{practiceSet.gaps.length > 0 && <div className="mt-8 border-t border-auth-green/10 pt-6"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">FOCUS AREAS</p><div className="mt-4"><GapPills gaps={practiceSet.gaps} /></div></div>}<button type="button" onClick={onStart} className={'mt-8 ' + primaryAction}>Start set <Icon className="size-4" /></button></section></div>;
}

function PracticeSession({ classroom, practiceSet, questions, questionIndex, answers, processing, onAnswer, onExit, onPrevious, onNext, onNavigate }) {
    const item = questions[questionIndex];
    const answered = Object.keys(answers).length;
    const canSubmit = Boolean(answers[item.id]) && !processing;
    return <div><button type="button" onClick={onExit} className="auth-focus inline-flex items-center gap-2 text-sm font-semibold text-auth-green">← Exit practice</button><div className="mt-4 flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-auth-muted">{classroom.subject}{practiceSet ? ` · ${practiceSet.title}` : ' · Adaptive practice'}</p><span className="rounded-full bg-auth-soft px-3 py-1.5 text-xs font-semibold text-auth-green">Question {questionIndex + 1} of {questions.length}</span></div><ProgressBar completed={answered} total={questions.length} label="Practice progress" /><div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.9fr)_minmax(17rem,.75fr)]"><section className="rounded-[1.75rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{item.topic.toUpperCase()}{practiceSet?.difficulty ? ` · ${practiceSet.difficulty.toUpperCase()}` : ''}</p><h1 className="mt-4 text-xl font-semibold leading-8 tracking-tight sm:text-2xl">{item.prompt}</h1><QuestionInput item={item} value={answers[item.id] || ''} onChange={onAnswer} /><p className="mt-4 text-xs text-auth-muted">Answers are graded the moment you submit and can't be changed afterward.</p><div className="mt-8 flex items-center justify-between gap-3"><button type="button" disabled={questionIndex === 0} onClick={onPrevious} className="auth-focus rounded-xl border border-auth-border px-4 py-2.5 text-sm font-semibold text-auth-green disabled:cursor-not-allowed disabled:opacity-40">← Previous</button><button type="button" disabled={!canSubmit} onClick={onNext} className={primaryAction + ' disabled:cursor-not-allowed disabled:opacity-50'}>{processing ? 'Submitting…' : questionIndex === questions.length - 1 ? 'Submit answer' : 'Save & next'} <Icon className="size-4" /></button></div></section>{practiceSet && <SetProgressCard practiceSet={practiceSet} questions={questions} answers={answers} current={questionIndex} onNavigate={onNavigate} />}</div></div>;
}

function QuestionInput({ item, value, onChange }) {
    return <div className="mt-6 grid gap-3 sm:grid-cols-2">{item.options.map((option, index) => <button key={option} type="button" onClick={() => onChange(option)} className={'auth-focus flex min-h-15 items-center gap-3 rounded-2xl border p-4 text-left text-sm font-semibold transition-colors ' + (value === option ? 'border-auth-green bg-auth-soft text-auth-green' : 'border-auth-border bg-white hover:border-auth-green/35')}><span className={'flex size-7 shrink-0 items-center justify-center rounded-full text-xs ' + (value === option ? 'bg-auth-green text-white' : 'bg-auth-page text-auth-muted')}>{value === option ? '✓' : String.fromCharCode(65 + index)}</span>{option}</button>)}</div>;
}

function SetProgressCard({ practiceSet, questions, answers, current, onNavigate }) {
    const answered = Object.keys(answers).length;
    return <aside className="rounded-[1.5rem] border border-auth-border bg-white p-5 shadow-xs"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">SET PROGRESS</p><h2 className="mt-2 text-lg font-semibold">{practiceSet.title}</h2><p className="mt-3 text-sm text-auth-muted">{answered} / {questions.length} answered</p><ProgressBar completed={answered} total={questions.length} label="Answered questions" /><div className="mt-6 grid grid-cols-5 gap-2">{questions.map((item, index) => <button key={item.id} type="button" onClick={() => onNavigate(index)} aria-label={'Question ' + (index + 1) + (answers[item.id] ? ', answered' : '')} className={'auth-focus aspect-square rounded-xl text-sm font-semibold ' + (index === current ? 'bg-auth-green text-white' : answers[item.id] ? 'bg-auth-soft text-auth-green' : 'bg-auth-page text-auth-muted')}>{answers[item.id] ? '✓' : index + 1}</button>)}</div>{practiceSet.gaps.length > 0 && <div className="mt-7 border-t border-auth-border pt-5"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">FOCUS</p><div className="mt-3"><GapPills gaps={practiceSet.gaps} /></div></div>}<p className="mt-5 text-sm text-auth-muted">Teacher: <span className="font-medium text-auth-ink">{practiceSet.teacher}</span></p></aside>;
}

function MasteryCard({ masteries }) {
    return <section className="rounded-[1.5rem] border border-auth-border bg-white p-5 shadow-xs sm:p-6"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">YOUR MASTERY</p><h2 className="mt-2 text-xl font-semibold tracking-tight">Keep building confidence</h2>{masteries.length ? <div className="mt-5 grid gap-4 sm:grid-cols-2">{masteries.map((item) => <div key={item.skill}><div className="flex justify-between text-xs text-auth-muted"><span>{item.skill}</span><span>{Math.round(item.mastery_score * 100)}%</span></div><ProgressBar completed={item.mastery_score * 100} total={100} label={item.skill + ' mastery'} /></div>)}</div> : <p className="mt-4 text-sm text-auth-muted">Your mastery will appear after your first adaptive answer.</p>}</section>;
}

