import { router, useForm, usePage, usePoll } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import StudentDashboard from '../Components/Student/StudentDashboard';
import { EmptyState } from '../Components/Student/DashboardComponents';
import { availableModes, homeworkRequest, isGrading } from '../Components/Student/homeworkRequest';

const filters = [
    { id: 'all', label: 'All' },
    { id: 'practice', label: 'Needs practice' },
    { id: 'review', label: 'Pending review' },
    { id: 'clean', label: 'Clean' },
    { id: 'marked', label: 'Marked by teacher' },
];

const statusStyle = { practice: 'bg-[#fff6d7] text-[#806d16]', clean: 'bg-auth-soft text-auth-green', review: 'bg-[#f7f7f2] text-auth-muted', marked: 'bg-auth-soft text-auth-green' };
const statusLabel = { practice: 'Needs practice', clean: 'Clean', review: 'Pending review', marked: 'Marked by teacher' };

const modeLabel = { pdf: 'PDF', photo: 'Photo', typed: 'Type answer' };

// Matches the `max:10240` (KB) rule in HomeworkController and SubmissionController.
const MAX_FILE_BYTES = 10 * 1024 * 1024;

export default function SubmitHomework(props) {
    return (
        <StudentDashboard {...props} initialView="submit">
            <SubmitHomeworkPage classroom={props.classroom} assignments={props.assignments} pastAttempts={props.pastAttempts} submissions={props.submissions} />
        </StudentDashboard>
    );
}

export function SubmitHomeworkPage({ classroom, assignments = [], pastAttempts = [], submissions = [] }) {
    const { flash } = usePage().props;
    const [filter, setFilter] = useState('all');
    const [activeAssignmentId, setActiveAssignmentId] = useState(null);

    const activeAssignment = assignments.find((assignment) => assignment.id === activeAssignmentId) ?? null;
    const grading = isGrading(submissions);
    const { start, stop } = usePoll(4000, { only: ['submissions', 'pastAttempts', 'assignments'] }, { autoStart: false });

    // usePoll returns new start/stop functions on every render, so only a
    // change in `grading` may restart the timer.
    useEffect(() => {
        if (!grading) return;

        start();

        return () => stop();
    }, [grading]);

    const newSubmission = submissions.find((submission) => submission.id === flash?.submission_id);

    const attemptsWithStatus = pastAttempts.map((attempt) => ({
        ...attempt,
        status: attempt.teacher_marked ? 'marked' : attempt.needs_review ? 'review' : attempt.is_correct ? 'clean' : 'practice',
    }));
    const visibleAttempts = filter === 'all' ? attemptsWithStatus : attemptsWithStatus.filter((attempt) => attempt.status === filter);
    const notSubmittedCount = assignments.filter((assignment) => !assignment.submitted).length;

    const diagnosis = flash?.homeworkDiagnosis;
    const flashResult = flash?.result;
    const hasGap = diagnosis && (diagnosis.conceptual?.tag || diagnosis.method?.issue || diagnosis.reasoning?.issue);

    return <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
        <section className="min-w-0 rounded-[2rem] border border-auth-green/10 bg-linear-to-br from-[#f5eeb0] via-[#f8f5d7] to-auth-soft p-5 shadow-xs sm:p-7 lg:p-8">
            <span className="inline-flex items-center gap-2 rounded-full border border-auth-green/15 bg-white/70 px-3 py-1.5 text-[11px] font-semibold text-auth-green"><span className="size-1.5 rounded-full bg-auth-green" />{classroom ? `${classroom.subject} · ${classroom.name}` : 'No classroom yet'}</span>
            <h2 className="mt-6 max-w-xl text-3xl font-semibold leading-[1.06] tracking-tight sm:text-4xl">Hand in your work. Get your thinking back.</h2>
            <p className="mt-4 max-w-xl text-sm leading-6 text-auth-muted">Every answer is read against the syllabus — the facts, the method, the reasoning. Your paper comes back marked up, with the misunderstanding named and practice built for it.</p>

            {!assignments.length && <div className="mt-7"><EmptyState title="No homework has been released yet." description="Your teacher hasn't assigned any homework to your class." /></div>}

            {assignments.length > 0 && <div className="mt-7 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-xs sm:p-5">
                <label className="block text-xs font-semibold text-auth-muted">Assignment
                    <select value={activeAssignmentId ?? ''} onChange={(event) => setActiveAssignmentId(Number(event.target.value))} className="auth-input mt-2 bg-white">
                        <option value="" disabled>Choose an assignment</option>
                        {assignments.map((assignment) => <option key={assignment.id} value={assignment.id}>{`${assignment.title}${assignment.submitted ? ' · Already submitted' : ''}`}</option>)}
                    </select>
                </label>

                {activeAssignment && <HomeworkForm key={activeAssignment.id} assignment={activeAssignment} onSubmitted={() => setActiveAssignmentId(null)} />}
            </div>}

            {newSubmission && <SubmissionResult submission={newSubmission} />}

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
            {submissions.length > 0 && <div className="mt-6"><h3 className="text-sm font-semibold">PDF submissions</h3><ul className="mt-3 grid gap-2">{submissions.map((submission) => <SubmissionRow key={submission.id} submission={submission} />)}</ul></div>}
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

export function HomeworkForm({ assignment, onSubmitted }) {
    const pdfInput = useRef(null);
    const photoInput = useRef(null);
    const cameraInput = useRef(null);
    const modes = availableModes(assignment);
    const [mode, setMode] = useState(modes[0]);
    const [fileError, setFileError] = useState('');
    const { data, setData, post, transform, processing, errors, clearErrors } = useForm({ pdf: null, photo: null, answer: '' });

    const request = homeworkRequest(mode, assignment, data);
    const error = fileError || Object.values(errors)[0];

    function selectMode(nextMode) {
        setMode(nextMode);
        setFileError('');
        clearErrors();
    }

    function selectFile(field, event) {
        const selectedFile = event.target.files?.[0] || null;
        event.target.value = '';

        if (!selectedFile) return;

        clearErrors();

        if (selectedFile.size > MAX_FILE_BYTES) {
            setFileError('Choose a file of 10 MB or less.');
            return;
        }

        setFileError('');
        setData(field, selectedFile);
    }

    function submit(event) {
        event.preventDefault();

        if (!request) return;

        transform(() => request.data);
        post(request.url, { preserveScroll: true, onSuccess: () => onSubmitted?.() });
    }

    return <form onSubmit={submit}>
        <ol className="mt-4 grid gap-3 rounded-xl bg-[#fafaf5] p-4 text-sm leading-6 text-auth-ink">
            {assignment.questions.map((question) => <li key={question.id} className="flex gap-3">{assignment.questions.length > 1 && <span className="font-semibold text-auth-green">{question.order}.</span>}<span>{question.body}</span></li>)}
        </ol>
        <p className="mt-2 text-xs text-auth-muted">
            {assignment.skill_name}{assignment.module_name ? ` · ${assignment.module_name}` : ''}
            {assignment.due_at ? ` · Due ${new Date(assignment.due_at).toLocaleString()}` : ''}
        </p>
        {assignment.submitted && <p className="mt-2 inline-flex rounded-full bg-auth-soft px-2.5 py-1 text-[11px] font-semibold text-auth-green">Already submitted — you can submit again</p>}

        {modes.length > 1 && <div className="mt-5 flex"><div className="inline-flex rounded-xl bg-[#f7f7f2] p-1" role="group" aria-label="How to hand in">{modes.map((item) => <button key={item} type="button" disabled={processing} onClick={() => selectMode(item)} aria-pressed={mode === item} className={`auth-focus rounded-lg px-3 py-2 text-xs font-semibold ${mode === item ? 'bg-white text-auth-ink shadow-xs' : 'text-auth-muted'}`}>{modeLabel[item]}</button>)}</div></div>}

        {mode === 'pdf' && <FileDrop title="Upload your answers as one PDF" description={assignment.questions.length > 1 ? 'Include every question and number your answers · up to 10 MB' : 'One PDF of your work · up to 10 MB'} file={data.pdf}>
            <input ref={pdfInput} type="file" accept="application/pdf,.pdf" onChange={(event) => selectFile('pdf', event)} className="sr-only" aria-label="Choose homework PDF" tabIndex={-1} />
            <button type="button" disabled={processing} onClick={() => pdfInput.current?.click()} className="auth-focus inline-flex items-center justify-center rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Choose PDF</button>
        </FileDrop>}

        {mode === 'photo' && <FileDrop title="Add a photo of your work" description="JPG or PNG · up to 10 MB" file={data.photo}>
            <input ref={photoInput} type="file" accept="image/jpeg,image/png" onChange={(event) => selectFile('photo', event)} className="sr-only" aria-label="Choose homework photo" tabIndex={-1} />
            <input ref={cameraInput} type="file" accept="image/jpeg,image/png" capture="environment" onChange={(event) => selectFile('photo', event)} className="sr-only" aria-label="Take a homework photo" tabIndex={-1} />
            <button type="button" disabled={processing} onClick={() => photoInput.current?.click()} className="auth-focus inline-flex items-center justify-center rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Choose photo</button>
            <button type="button" disabled={processing} onClick={() => cameraInput.current?.click()} className="auth-focus inline-flex items-center gap-2 rounded-xl border border-auth-border bg-white px-5 py-3 text-sm font-semibold text-auth-ink hover:bg-auth-page"><CameraIcon />Use camera</button>
        </FileDrop>}

        {mode === 'typed' && <label className="mt-4 block text-sm font-semibold">Your answer
            <textarea value={data.answer} onChange={(event) => setData('answer', event.target.value)} className="auth-focus mt-2 min-h-32 w-full rounded-xl border border-auth-border bg-[#fafaf5] p-4 text-sm font-normal" placeholder="Explain your reasoning, not just the result." />
        </label>}

        {error && <p className="mt-4 text-sm font-medium text-red-700" role="alert">{error}</p>}

        <button type="submit" disabled={!request || processing} className="auth-focus mt-6 flex w-full items-center justify-between rounded-xl bg-auth-green px-5 py-3.5 text-sm font-semibold text-white hover:bg-auth-green/90 disabled:cursor-not-allowed disabled:bg-auth-green/35"><span>{processing ? 'Submitting…' : 'Submit homework'}</span><ArrowIcon /></button>
        <p className="mt-3 text-center text-[11px] leading-5 text-auth-muted" aria-live="polite">{processing && mode !== 'pdf' ? 'This can take a minute. Keep this page open.' : 'Your teacher sets the mark. Celik only points to where the reasoning turns.'}</p>
    </form>;
}

function FileDrop({ title, description, file, children }) {
    return <div className="mt-4 rounded-2xl border border-dashed border-auth-green/30 bg-[#fafaf5] px-5 py-8 text-center sm:px-8">
        <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-auth-soft text-auth-green" aria-hidden="true"><UploadIcon /></span>
        <h3 className="mt-4 text-lg font-semibold tracking-tight">{title}</h3>
        <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-auth-muted">{description}</p>
        <div className="mt-6 flex flex-wrap justify-center gap-3 border-t border-auth-border pt-5">{children}</div>
        {file && <div className="mt-5 flex items-center gap-3 rounded-xl border border-auth-border bg-white px-4 py-3 text-left"><span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-auth-soft text-auth-green"><UploadIcon className="size-4" /></span><div className="min-w-0"><p className="truncate text-sm font-semibold">{file.name}</p><p className="mt-0.5 text-xs text-auth-muted">{(file.size / 1024 / 1024).toFixed(1)} MB · ready to submit</p></div></div>}
    </div>;
}

function SubmissionResult({ submission }) {
    const unread = submission.question_count - submission.answered_count;

    if (submission.extraction_status === 'pending') return <ResultPanel title={`${submission.assignment_title} was handed in.`}>
        <p>Being graded now. This can take a few minutes, and you can leave this page.</p>
    </ResultPanel>;

    if (submission.extraction_status === 'failed') return <ResultPanel title={`${submission.assignment_title} could not be read.`}>
        <p>Nothing was graded. Please hand it in again in a few minutes.</p>
    </ResultPanel>;

    return <ResultPanel title={`${submission.assignment_title} was graded.`}>
        <p>{`${submission.answered_count} of ${submission.question_count} answers read.`}{submission.needs_review_count > 0 && ` ${submission.needs_review_count} waiting for your teacher.`}</p>
        {unread > 0 && <p>Answers that could not be matched to a question were not graded. Number your answers clearly and submit again if something is missing.</p>}
    </ResultPanel>;
}

function ResultPanel({ title, children }) {
    return <div className="mt-5 rounded-2xl border border-auth-border bg-white p-5" role="status">
        <p className="text-sm font-semibold">{title}</p>
        <div className="mt-2 grid gap-2 text-sm text-auth-muted">{children}</div>
    </div>;
}

function submissionStatus(submission) {
    if (submission.extraction_status === 'failed') return { label: 'Could not be read', style: 'bg-[#f9e5de] text-[#a44d3b]' };
    if (submission.extraction_status !== 'extracted') return { label: 'Being graded', style: statusStyle.review };
    if (submission.teacher_validated_at) return { label: 'Checked by teacher', style: statusStyle.clean };
    if (submission.needs_review_count > 0) return { label: 'Waiting for teacher', style: statusStyle.practice };

    return { label: 'Graded', style: statusStyle.clean };
}

function SubmissionRow({ submission }) {
    const status = submissionStatus(submission);

    return <li className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-auth-border px-4 py-3">
        <div><p className="text-sm font-semibold">{submission.assignment_title}</p><p className="mt-0.5 text-xs text-auth-muted">{submission.extraction_status === 'extracted' && `${submission.answered_count} of ${submission.question_count} answers read · `}{new Date(submission.created_at).toLocaleDateString()}</p></div>
        <span className={`rounded-md px-2 py-1 text-[10px] font-bold ${status.style}`}>{status.label}</span>
    </li>;
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
