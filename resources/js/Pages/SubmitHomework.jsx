import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import StudentDashboard from '../Components/Student/StudentDashboard';

const releasedHomework = [
    { id: 1, mark: '14/20', title: 'Homework 6 · Heat and temperature', code: 'S-04', finding: 'Metal is naturally colder than wood', released: 'Released 2 Sep · Cikgu Rahimah · 3 practice questions', status: 'practice', fresh: true },
    { id: 2, mark: '17/20', title: 'Homework 5 · Energy changes', code: 'A-01', finding: 'States without explaining', released: 'Released 28 Aug · Cikgu Rahimah · 2 practice questions', status: 'practice' },
    { id: 3, mark: '20/20', title: 'Homework 4 · Measurement', code: 'Clean', finding: 'No misconception found — method sound throughout', released: 'Released 24 Aug · Cikgu Rahimah', status: 'clean' },
    { id: 4, mark: '12/20', title: 'Homework 3 · Simple machines', code: 'S-08', finding: 'Heat rises, rather than hot air rises', released: 'Released 19 Aug · Cikgu Rahimah · practice completed', status: 'practice' },
];

const filters = [
    { id: 'all', label: 'All' },
    { id: 'practice', label: 'Needs practice' },
    { id: 'clean', label: 'Clean' },
];

export default function SubmitHomework(props) {
    return <StudentDashboard {...props} initialView="submit"><SubmitHomeworkPage /></StudentDashboard>;
}

export function SubmitHomeworkPage() {
    const fileInput = useRef(null);
    const cameraInput = useRef(null);
    const [file, setFile] = useState(null);
    const [fileError, setFileError] = useState('');
    const [filter, setFilter] = useState('all');
    const visibleHomework = filter === 'all' ? releasedHomework : releasedHomework.filter((homework) => homework.status === filter);

    function selectFile(event) {
        const selectedFile = event.target.files?.[0] || null;

        if (selectedFile && selectedFile.size > 20 * 1024 * 1024) {
            setFile(null);
            setFileError('Choose a file smaller than 20 MB.');
            event.target.value = '';
            return;
        }

        setFile(selectedFile);
        setFileError('');
    }

    return <div className="grid gap-6 xl:grid-cols-2 xl:items-start">
        <section className="min-w-0 rounded-[2rem] border border-auth-green/10 bg-linear-to-br from-[#f5eeb0] via-[#f8f5d7] to-auth-soft p-5 shadow-xs sm:p-7 lg:p-8">
            <span className="inline-flex items-center gap-2 rounded-full border border-auth-green/15 bg-white/70 px-3 py-1.5 text-[11px] font-semibold text-auth-green"><span className="size-1.5 rounded-full bg-auth-green" />Science · Form 2 · KSSM</span>
            <h2 className="mt-6 max-w-xl text-3xl font-semibold leading-[1.06] tracking-tight sm:text-4xl">Hand in your work. Get your thinking back.</h2>
            <p className="mt-4 max-w-xl text-sm leading-6 text-auth-muted">Every answer is read against the syllabus — the facts, the method, the reasoning. Your paper comes back marked up, with the misunderstanding named and practice built for it.</p>

            <div className="mt-7 rounded-[1.5rem] border border-white/80 bg-white p-4 shadow-xs sm:p-5">
                <div className="rounded-2xl border border-dashed border-auth-green/30 bg-[#fafaf5] px-5 py-8 text-center sm:px-8">
                    <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-auth-soft text-auth-green" aria-hidden="true"><UploadIcon /></span>
                    <h3 className="mt-4 text-lg font-semibold tracking-tight">Drop your homework here</h3>
                    <p className="mx-auto mt-2 max-w-sm text-sm leading-6 text-auth-muted">A photo of your written work, or a PDF · up to 20 MB · pages stay in order</p>
                    <input ref={fileInput} type="file" accept="image/*,.pdf,application/pdf" onChange={selectFile} className="sr-only" aria-label="Choose homework file" />
                    <input ref={cameraInput} type="file" accept="image/*" capture="environment" onChange={selectFile} className="sr-only" aria-label="Take a homework photo" />
                    <div className="mt-6 flex flex-wrap justify-center gap-3 border-t border-auth-border pt-5">
                        <button type="button" onClick={() => fileInput.current?.click()} className="auth-focus inline-flex items-center justify-center rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Choose file</button>
                        <button type="button" onClick={() => cameraInput.current?.click()} className="auth-focus inline-flex items-center gap-2 rounded-xl border border-auth-border bg-white px-5 py-3 text-sm font-semibold text-auth-ink hover:bg-auth-page"><CameraIcon />Use camera</button>
                    </div>
                    {file && <div className="mt-5 flex items-center gap-3 rounded-xl border border-auth-border bg-white px-4 py-3 text-left"><span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-auth-soft text-auth-green"><UploadIcon className="size-4" /></span><div className="min-w-0"><p className="truncate text-sm font-semibold">{file.name}</p><p className="mt-0.5 text-xs text-auth-muted">{(file.size / 1024 / 1024).toFixed(1)} MB · ready to attach</p></div></div>}
                    {fileError && <p className="mt-4 text-sm font-medium text-red-700" role="alert">{fileError}</p>}
                </div>

                <div className="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_11.5rem]">
                    {/* TODO BACKEND: Replace assignment and due-date samples with assignment data. */}
                    <label className="min-w-0 text-xs font-semibold text-auth-muted">Assignment<select defaultValue="homework-7" className="auth-input mt-2 bg-white"><option value="homework-7">Homework 7 · Heat and energy transfer</option></select></label>
                    <label className="text-xs font-semibold text-auth-muted">Due<span className="auth-input mt-2 flex items-center gap-2 bg-[#fafaf5] text-auth-ink"><CalendarIcon />Friday, 8:00 am</span></label>
                </div>

                {/* TODO BACKEND: Submit selected homework when a submission endpoint is available. */}
                <button type="button" disabled={!file} className="auth-focus mt-6 flex w-full items-center justify-between rounded-xl bg-auth-green px-5 py-3.5 text-sm font-semibold text-white hover:bg-auth-green/90 disabled:cursor-not-allowed disabled:bg-auth-green/35"><span>{file ? 'Submit homework' : 'Attach your homework first'}</span><ArrowIcon /></button>
                <p className="mt-3 text-center text-[11px] leading-5 text-auth-muted">Your teacher sets the mark. Celik only points to where the reasoning turns.</p>
            </div>

            {/* TODO BACKEND: Replace summary values with student submission statistics. */}
            <dl className="mt-5 grid grid-cols-3 gap-3">{[
                ['24', 'Submitted'],
                ['6', 'Misconceptions cleared'],
                ['3', 'Practice sets waiting'],
            ].map(([value, label]) => <div key={label} className="min-w-0 rounded-2xl border border-white/80 bg-white/65 p-3 text-center sm:p-4"><dd className="text-2xl font-semibold tracking-tight text-auth-green">{value}</dd><dt className="mt-1 text-xs leading-4 text-auth-muted">{label}</dt></div>)}</dl>
        </section>

        <section className="min-w-0 rounded-[2rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7 lg:p-8">
            <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="text-2xl font-semibold tracking-tight">Released to you</h2><p className="mt-3 max-w-lg text-sm leading-6 text-auth-muted">Work your teacher has reviewed and approved. Open one to see the highlights and the misconception behind each.</p></div><span className="rounded-full bg-[#f4eda6] px-3 py-1.5 text-xs font-semibold text-auth-ink">4 marked · 1 new</span></div>
            <div className="mt-6 inline-flex rounded-xl bg-[#f7f7f2] p-1" aria-label="Filter released homework">{filters.map((item) => <button key={item.id} type="button" onClick={() => setFilter(item.id)} aria-pressed={filter === item.id} className={`auth-focus rounded-lg px-3 py-2 text-xs font-semibold sm:px-4 ${filter === item.id ? 'bg-white text-auth-ink shadow-xs' : 'text-auth-muted hover:text-auth-ink'}`}>{item.label}</button>)}</div>

            {/* TODO BACKEND: Replace released homework samples with teacher-released work. */}
            <div className="mt-5 space-y-3">{visibleHomework.map((homework) => <article key={homework.id} className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)_auto] gap-3 rounded-2xl border border-auth-border p-4 transition-colors hover:border-auth-green/25 sm:gap-4 sm:p-5">
                <div className="flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-[#f7f7f2] text-auth-green"><span className="text-lg font-semibold leading-none">{homework.mark.split('/')[0]}</span><span className="mt-1 text-[10px] text-auth-muted">/20</span></div>
                <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="text-sm font-semibold leading-5">{homework.title}</h3>{homework.fresh && <span className="rounded-full bg-[#f4eda6] px-2 py-0.5 text-[10px] font-bold text-auth-ink">NEW</span>}</div><p className="mt-2 text-sm leading-5"><span className={`mr-2 inline-block rounded-md px-2 py-1 text-[10px] font-bold ${homework.status === 'clean' ? 'bg-auth-soft text-auth-green' : 'bg-[#fff6d7] text-[#806d16]'}`}>{homework.code}</span>{homework.finding}</p><p className="mt-3 text-xs leading-5 text-auth-muted">{homework.released}</p></div>
                {/* TODO BACKEND: Link to released homework details when a detail route exists. */}
                <button type="button" aria-label={`Preview ${homework.title}`} className="auth-focus self-center rounded-full border border-auth-border p-2.5 text-auth-green hover:bg-auth-soft" title="Preview unavailable"><ArrowIcon className="size-4" /></button>
            </article>)}</div>

            <div className="mt-6 rounded-2xl border border-auth-green/10 bg-linear-to-br from-[#f5eeb0] to-auth-soft p-5 sm:p-6"><p className="text-[11px] font-bold tracking-widest text-auth-green">YOUR NEXT PRACTICE · S-04</p><h3 className="mt-3 text-xl font-semibold tracking-tight">Two questions built for the mistake you made</h3><p className="mt-3 text-sm leading-6 text-auth-muted">Traced to learning standard 4.2.1 — metal conducts heat away from your hand faster than wood.</p><button type="button" onClick={() => router.visit('/practice')} className="auth-focus mt-5 inline-flex items-center gap-2 rounded-xl bg-auth-green px-5 py-3 text-sm font-semibold text-white hover:bg-auth-green/90">Start practice <ArrowIcon className="size-4" /></button></div>
        </section>
    </div>;
}

function UploadIcon({ className = 'size-5' }) {
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M12 16V3m-5 5 5-5 5 5M4 15v5h16v-5" /></svg>;
}

function CameraIcon() {
    return <svg aria-hidden="true" className="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm8 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" /></svg>;
}

function CalendarIcon() {
    return <svg aria-hidden="true" className="size-4 shrink-0 text-auth-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M4 5h16v15H4zM8 3v4m8-4v4M4 10h16" /></svg>;
}

function ArrowIcon({ className = 'size-5' }) {
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14m-5-5 5 5-5 5" /></svg>;
}
