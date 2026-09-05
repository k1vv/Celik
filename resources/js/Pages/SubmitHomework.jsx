import { router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import StudentDashboard from '../Components/Student/StudentDashboard';
import { EmptyState } from '../Components/Student/DashboardComponents';

const filters = [
    { id: 'all', label: 'All' },
    { id: 'practice', label: 'Needs practice' },
    { id: 'review', label: 'Pending review' },
    { id: 'clean', label: 'Clean' },
];

const statusStyle = { practice: 'bg-[#fff6d7] text-[#806d16]', clean: 'bg-auth-soft text-auth-green', review: 'bg-[#f7f7f2] text-auth-muted' };
const statusLabel = { practice: 'Needs practice', clean: 'Clean', review: 'Pending review' };

// Matches HomeworkController's `max:10240` (KB) validation rule.
const MAX_FILE_BYTES = 10 * 1024 * 1024;

export default function SubmitHomework(props) {
    return (
        <StudentDashboard {...props} initialView="submit">
            <SubmitHomeworkPage classroom={props.classroom} assignments={props.assignments} pastAttempts={props.pastAttempts} />
        </StudentDashboard>
    );
}

export function SubmitHomeworkPage({ classroom, assignments = [], pastAttempts = [] }) {
    const { flash } = usePage().props;
    const fileInput = useRef(null);
    const cameraInput = useRef(null);
    const [mode, setMode] = useState('file');
    const [fileError, setFileError] = useState('');
    const [filter, setFilter] = useState('all');
    const [activeAssignmentId, setActiveAssignmentId] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        question_id: '',
        answer: '',
        photo: null,
    });

    const activeAssignment = assignments.find((assignment) => assignment.id === activeAssignmentId) ?? null;
    const submittedQuestionIds = new Set(pastAttempts.map((attempt) => attempt.question_id));

    function selectAssignment(assignment) {
        setActiveAssignmentId(assignment.id);
        setData((current) => ({ ...current, question_id: assignment.question_id, answer: '', photo: null }));
        setMode('file');
        setFileError('');
        if (fileInput.current) fileInput.current.value = '';
        if (cameraInput.current) cameraInput.current.value = '';
    }

    function selectFile(event) {
        const selectedFile = event.target.files?.[0] || null;

        if (selectedFile && selectedFile.size > MAX_FILE_BYTES) {
            setFileError('Choose a file smaller than 10 MB.');
            event.target.value = '';
            return;
        }

        setFileError('');
        setData('photo', selectedFile);
    }

    function submit(event) {
        event.preventDefault();
        post('/homework', {
            preserveScroll: true,
            onSuccess: () => {
                reset('answer', 'photo');
                setActiveAssignmentId(null);
                if (fileInput.current) fileInput.current.value = '';
                if (cameraInput.current) cameraInput.current.value = '';
            },
        });
    }

    const canSubmit = data.question_id && (mode === 'typed' ? data.answer.trim().length > 0 : !!data.photo);

    const attemptsWithStatus = pastAttempts.map((attempt) => ({
        ...attempt,
        status: attempt.needs_review ? 'review' : attempt.is_correct ? 'clean' : 'practice',
    }));
    const visibleAttempts = filter === 'all' ? attemptsWithStatus : attemptsWithStatus.filter((attempt) => attempt.status === filter);
    const notSubmittedCount = assignments.filter((assignment) => !submittedQuestionIds.has(assignment.question_id)).length;

    const diagnosis = flash?.homeworkDiagnosis;
    const flashResult = flash?.result;
    const hasGap = diagnosis && (diagnosis.conceptual?.tag || diagnosis.method?.issue || diagnosis.reasoning?.issue);

    return <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
        <section className="min-w-0 rounded-[2rem] border border-auth-green/10 bg-linear-to-br from-[#f5eeb0] via-[#f8f5d7] to-auth-soft p-5 shadow-xs sm:p-7 lg:p-8">
            <span className="inline-flex items-center gap-2 rounded-full border border-auth-green/15 bg-white/70 px-3 py-1.5 text-[11px] font-semibold text-auth-green"><span className="size-1.5 rounded-full bg-auth-green" />{classroom ? `${classroom.subject} · ${classroom.name}` : 'No classroom yet'}</span>
            <h2 className="mt-6 max-w-xl text-3xl font-semibold leading-[1.06] tracking-tight sm:text-4xl">Hand in your work. Get your thinking back.</h2>
            <p className="mt-4 max-w-xl text-sm leading-6 text-auth-muted">Every answer is read against the syllabus — the facts, the method, the reasoning. Your paper comes back marked up, with the misunderstanding named and practice built for it.</p>

            {!assignments.length && <div className="mt-7"><EmptyState title="No homework has been released yet." description="Your teacher hasn't assigned any homework to your class." /></div>}

            {assignments.length > 0 && <form onSubmit={submit} className="mt-7 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-xs sm:p-5">
                <label className="block text-xs font-semibold text-auth-muted">Assignment
                    <select
                        value={activeAssignmentId ?? ''}
                        onChange={(event) => {
                            const assignment = assignments.find((item) => item.id === Number(event.target.value));
                            if (assignment) selectAssignment(assignment);
                        }}
                        className="auth-input mt-2 bg-white"
                    >
                        <option value="" disabled>Choose an assignment</option>
                        {assignments.map((assignment) => <option key={assignment.id} value={assignment.id}>
                            {assignment.title}{submittedQuestionIds.has(assignment.question_id) ? ' · Already submitted' : ''}
                        </option>)}
                    </select>
                </label>

                {activeAssignment && <>
                    <p className="mt-4 rounded-xl bg-[#fafaf5] p-4 text-sm leading-6 text-auth-ink">{activeAssignment.question_body}</p>
                    <p className="mt-2 text-xs text-auth-muted">
                        {activeAssignment.skill_name}{activeAssignment.module_name ? ` · ${activeAssignment.module_name}` : ''}
                        {activeAssignment.due_at ? ` · Due ${new Date(activeAssignment.due_at).toLocaleString()}` : ''}
                    </p>
                    {submittedQuestionIds.has(activeAssignment.question_id) && <p className="mt-2 inline-flex rounded-full bg-auth-soft px-2.5 py-1 text-[11px] font-semibold text-auth-green">Already submitted — you can submit again</p>}

                    <div className="mt-5 inline-flex rounded-xl bg-[#f7f7f2] p-1">
                        <button type="button" onClick={() => setMode('file')} aria-pressed={mode === 'file'} className={`rounded-lg px-3 py-2 text-xs font-semibold ${mode === 'file' ? 'bg-white text-auth-ink shadow-xs' : 'text-auth-muted'}`}>Photo or PDF</button>
                        <button type="button" onClick={() => setMode('typed')} aria-pressed={mode === 'typed'} className={`rounded-lg px-3 py-2 text-xs font-semibold ${mode === 'typed' ? 'bg-white text-auth-ink shadow-xs' : 'text-auth-muted'}`}>Type answer</button>
                    </div>

                    {mode === 'file' ? <div className="mt-4 rounded-2xl border border-dashed border-auth-green/30 bg-[#fafaf5] px-5 py-8 text-center sm:px-8">
                        <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-auth-soft text-auth-green" aria-hidden="true"><UploadIcon /></span>
                        <h3 className="mt-4 text-lg font-semibold tracking-tight">Drop your homework here</h3>
                        <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-auth-muted">A photo of your written work, or a PDF · up to 10 MB</p>
                        <input ref={fileInput} type="file" accept="image/*,.pdf,application/pdf" onChange={selectFile} className="sr-only" aria-label="Choose homework file" />
                        <input ref={cameraInput} type="file" accept="image/*" capture="environment" onChange={selectFile} className="sr-only" aria-label="Take a homework photo" />
                        <div className="mt-6 flex flex-wrap justify-center gap-3 border-t border-auth-border pt-5">
                            <button type="button" onClick={() => fileInput.current?.click()} className="auth-focus inline-flex items-center justify-center rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Choose file</button>
                            <button type="button" onClick={() => cameraInput.current?.click()} className="auth-focus inline-flex items-center gap-2 rounded-xl border border-auth-border bg-white px-5 py-3 text-sm font-semibold text-auth-ink hover:bg-auth-page"><CameraIcon />Use camera</button>
                        </div>
                        {data.photo && <div className="mt-5 flex items-center gap-3 rounded-xl border border-auth-border bg-white px-4 py-3 text-left"><span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-auth-soft text-auth-green"><UploadIcon className="size-4" /></span><div className="min-w-0"><p className="truncate text-sm font-semibold">{data.photo.name}</p><p className="mt-0.5 text-xs text-auth-muted">{(data.photo.size / 1024 / 1024).toFixed(1)} MB · ready to attach</p></div></div>}
                        {fileError && <p className="mt-4 text-sm font-medium text-red-700" role="alert">{fileError}</p>}
                    </div> : <label className="mt-4 block text-sm font-semibold">Your answer
                        <textarea value={data.answer} onChange={(event) => setData('answer', event.target.value)} className="auth-focus mt-2 min-h-32 w-full rounded-xl border border-auth-border bg-[#fafaf5] p-4 text-sm font-normal" placeholder="Explain your reasoning, not just the result." />
                    </label>}

                    <button type="submit" disabled={!canSubmit || processing} className="auth-focus mt-6 flex w-full items-center justify-between rounded-xl bg-auth-green px-5 py-3.5 text-sm font-semibold text-white hover:bg-auth-green/90 disabled:cursor-not-allowed disabled:bg-auth-green/35"><span>{processing ? 'Submitting…' : 'Submit homework'}</span><ArrowIcon /></button>
                    <p className="mt-3 text-center text-[11px] leading-5 text-auth-muted">Your teacher sets the mark. Celik only points to where the reasoning turns.</p>
                </>}
            </form>}

            {flashResult && <div className="mt-5 rounded-2xl border border-auth-border bg-white p-5">
                <p className="text-sm font-semibold">{flashResult === 'correct' ? 'Correct — no gaps found.' : flashResult === 'pending_review' ? 'Submitted — pending teacher review.' : 'Recorded — here’s what to work on.'}</p>
                {hasGap && <div className="mt-3 space-y-2 text-sm text-auth-muted">
                    {diagnosis.conceptual?.tag && <p><span className="font-semibold text-auth-ink">Conceptual:</span> {diagnosis.conceptual.tag}{diagnosis.conceptual.evidence && ` — “${diagnosis.conceptual.evidence}”`}</p>}
                    {diagnosis.method?.issue && <p><span className="font-semibold text-auth-ink">Method:</span> {diagnosis.method.issue}{diagnosis.method.evidence && ` — “${diagnosis.method.evidence}”`}</p>}
                    {diagnosis.reasoning?.issue && <p><span className="font-semibold text-auth-ink">Reasoning:</span> {diagnosis.reasoning.issue}{diagnosis.reasoning.evidence && ` — “${diagnosis.reasoning.evidence}”`}</p>}
                </div>}
            </div>}

            <dl className="mt-5 grid grid-cols-3 gap-3">
                <StatTile value={pastAttempts.length} label="Submitted" />
                <StatTile value={attemptsWithStatus.filter((attempt) => attempt.status === 'practice').length} label="Needs practice" />
                <StatTile value={notSubmittedCount} label="Assignments waiting" />
            </dl>
        </section>

        <section className="min-w-0 rounded-[2rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7 lg:p-8">
            <div><h2 className="text-2xl font-semibold tracking-tight">Released to you</h2><p className="mt-3 max-w-lg text-sm leading-6 text-auth-muted">Every homework you've submitted, with the diagnosis behind each result.</p></div>
            <div className="mt-6 inline-flex flex-wrap rounded-xl bg-[#f7f7f2] p-1" aria-label="Filter released homework">{filters.map((item) => <button key={item.id} type="button" onClick={() => setFilter(item.id)} aria-pressed={filter === item.id} className={`auth-focus rounded-lg px-3 py-2 text-xs font-semibold sm:px-4 ${filter === item.id ? 'bg-white text-auth-ink shadow-xs' : 'text-auth-muted hover:text-auth-ink'}`}>{item.label}</button>)}</div>

            <div className="mt-5 space-y-3">
                {!visibleAttempts.length && <EmptyState title="Nothing here yet." description="Your submitted homework will appear once you turn something in." />}
                {visibleAttempts.map((attempt) => <AttemptCard key={attempt.id} attempt={attempt} />)}
            </div>

            <div className="mt-6 rounded-2xl border border-auth-green/10 bg-linear-to-br from-[#f5eeb0] to-auth-soft p-5 sm:p-6">
                <h3 className="text-xl font-semibold tracking-tight">Want more practice?</h3>
                <p className="mt-3 text-sm leading-6 text-auth-muted">Your adaptive practice picks the skill you need most right now.</p>
                <button type="button" onClick={() => router.visit('/practice')} className="auth-focus mt-5 inline-flex items-center gap-2 rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Start practice <ArrowIcon className="size-4" /></button>
            </div>
        </section>
    </div>;
}

function StatTile({ value, label }) {
    return <div className="min-w-0 rounded-2xl border border-white/80 bg-white/65 p-3 text-center sm:p-4"><dd className="text-2xl font-semibold tracking-tight text-auth-green">{value}</dd><dt className="mt-1 text-xs leading-4 text-auth-muted">{label}</dt></div>;
}

function AttemptCard({ attempt }) {
    return <article className="rounded-2xl border border-auth-border p-4 sm:p-5">
        <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="text-sm font-semibold leading-5">{attempt.question_body}</h3>
            <div className="flex items-center gap-2">
                {attempt.score !== null && <span className="rounded-md bg-[#f7f7f2] px-2 py-1 text-[10px] font-bold text-auth-ink">{attempt.score}/{attempt.max_score}</span>}
                <span className={`rounded-md px-2 py-1 text-[10px] font-bold ${statusStyle[attempt.status]}`}>{statusLabel[attempt.status]}</span>
            </div>
        </div>
        <p className="mt-2 text-xs text-auth-muted">{attempt.kssm_code} · {new Date(attempt.created_at).toLocaleDateString()}</p>
        {attempt.diagnoses.length > 0 && <div className="mt-3 space-y-1.5 text-xs text-auth-muted">{attempt.diagnoses.map((diagnosis, index) => <p key={index}><span className="font-semibold text-auth-ink capitalize">{diagnosis.dimension}:</span> {diagnosis.tag}{diagnosis.evidence && ` — “${diagnosis.evidence}”`}</p>)}</div>}
    </article>;
}

function UploadIcon({ className = 'size-5' }) {
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5" /></svg>;
}

function CameraIcon() {
    return <svg aria-hidden="true" className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm8 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>;
}

function ArrowIcon({ className = 'size-5' }) {
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14m-5-5 5 5-5 5" /></svg>;
}
