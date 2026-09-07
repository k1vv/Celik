import { router } from '@inertiajs/react';
import { EmptyState, Icon } from './DashboardComponents';

const primaryAction = 'auth-focus inline-flex items-center justify-center gap-2 rounded-xl bg-auth-green px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-auth-green/90';
const dueStyles = { overdue: 'bg-[#fff2d7] text-[#8b6318]', upcoming: 'bg-auth-soft text-auth-green' };

export function PageSummary({ items }) {
    return <dl className="flex flex-wrap gap-3">{items.map((item) => <div key={item.label} className="min-w-30 rounded-2xl border border-white/80 bg-white/75 px-4 py-3 shadow-xs"><dd className="text-2xl font-semibold tracking-tight text-auth-green">{item.value}</dd><dt className="mt-1 text-[11px] font-medium text-auth-muted">{item.label}</dt></div>)}</dl>;
}

/**
 * Shows the student's most recent marked homework - real diagnosis evidence
 * highlighted directly inside their own answer text (evidence is always an
 * exact quoted substring of raw_answer, see architecture.md), not a
 * hand-crafted mock highlight.
 */
export function MarkedWorkPage({ attempts = [] }) {
    const work = attempts.find((attempt) => attempt.diagnoses.length > 0) ?? attempts[0];

    if (!work) return <div><PageHeader eyebrow="RECENTLY MARKED" title="Marked work" description="See the diagnosis behind each homework result." /><div className="mt-6"><EmptyState title="Nothing marked yet." description="Submit homework to see your results here." /></div></div>;

    return <div className="grid gap-5 lg:gap-6">
        <section className="overflow-hidden rounded-[1.75rem] border border-auth-green/10 bg-linear-to-br from-[#fff2a8] via-[#f8f5d8] to-auth-soft p-6 shadow-xs sm:p-8">
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{new Date(work.created_at).toLocaleDateString().toUpperCase()}</p>
                    <h1 className="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">Your marked answer</h1>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-auth-muted">{work.question_body}. {work.diagnoses.length} issue{work.diagnoses.length === 1 ? '' : 's'} tagged to your syllabus gaps.</p>
                </div>
                <PageSummary items={[
                    { label: 'Result', value: work.teacher_marked ? 'Marked by teacher' : work.needs_review ? 'Pending review' : work.is_correct ? 'Correct' : 'Practice needed' },
                    ...(work.score !== null ? [{ label: 'Score', value: `${work.score}/${work.max_score}` }] : []),
                ]} />
            </div>
        </section>
        <div className="grid gap-5 xl:grid-cols-[minmax(0,1.85fr)_minmax(18rem,1fr)] xl:gap-6">
            <section className="rounded-[1.75rem] border border-auth-border bg-white p-5 shadow-xs sm:p-7">
                <div className="border-b border-auth-border pb-5"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">QUESTION</p><h2 className="mt-2 text-xl font-semibold tracking-tight sm:text-2xl">{work.question_body}</h2></div>
                {work.raw_answer ? <div className="mt-6 rounded-2xl border border-auth-border bg-[#fcfcf9] p-5 text-lg leading-8 text-auth-ink sm:p-6"><p>{highlightEvidence(work.raw_answer, work.diagnoses)}</p></div> : <p className="mt-6 text-sm text-auth-muted">This answer was submitted as a photo, so the original text isn't shown here.</p>}
                <div className="mt-8"><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">WHAT WAS FOUND</p><div className="mt-4 grid gap-3">{work.diagnoses.length ? work.diagnoses.map((diagnosis, index) => <IssueCard key={index} diagnosis={diagnosis} />) : <EmptyState title="No issues found." description="This answer was fully correct." />}</div></div>
            </section>
            <aside className="grid content-start gap-5">
                <PracticeCard hasGaps={work.diagnoses.length > 0} />
            </aside>
        </div>
    </div>;
}

/** Wraps each diagnosis's quoted evidence in <mark> within the real answer text. */
function highlightEvidence(rawAnswer, diagnoses) {
    const evidencePhrases = diagnoses.map((d) => d.evidence).filter(Boolean);
    if (!evidencePhrases.length) return rawAnswer;

    let remaining = rawAnswer;
    const parts = [];
    while (remaining.length) {
        const matches = evidencePhrases.map((phrase) => ({ phrase, index: remaining.indexOf(phrase) })).filter((m) => m.index !== -1).sort((a, b) => a.index - b.index);
        if (!matches.length) {
            parts.push(remaining);
            break;
        }
        const { phrase, index } = matches[0];
        if (index > 0) parts.push(remaining.slice(0, index));
        parts.push(<mark key={parts.length} className="rounded bg-[#f9e5de] px-1.5 py-0.5 text-inherit">{phrase}</mark>);
        remaining = remaining.slice(index + phrase.length);
    }
    return parts;
}

export function IssueCard({ diagnosis }) {
    return <article className="rounded-2xl border border-[#f3d2c8] bg-[#fff8f5] p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <h3 className="text-sm font-semibold capitalize leading-5">{diagnosis.dimension}</h3>
            <span className="rounded-full bg-[#f9e5de] px-2.5 py-1 text-[10px] font-bold text-[#a44d3b]">{diagnosis.tag.replace(/_/g, ' ')}</span>
        </div>
        {diagnosis.evidence && <p className="mt-3 text-sm leading-6 text-auth-muted">“{diagnosis.evidence}”</p>}
    </article>;
}

function PracticeCard({ hasGaps }) {
    return <section className="rounded-[1.75rem] border border-auth-green/10 bg-linear-to-br from-[#fff2a8] to-auth-soft p-5 shadow-xs sm:p-6">
        <p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">NEXT</p>
        <h2 className="mt-3 text-xl font-semibold tracking-tight">{hasGaps ? 'Practice built for this' : 'Keep up the streak'}</h2>
        <p className="mt-3 text-sm leading-6 text-auth-muted">{hasGaps ? "Your adaptive practice targets the skill this answer surfaced a gap in." : 'No gaps here — adaptive practice still adjusts to your overall mastery.'}</p>
        <button type="button" onClick={() => router.visit('/practice')} className={`${primaryAction} mt-5`}>Go to practice <Icon className="size-4" /></button>
    </section>;
}

/** Assignments the student hasn't submitted yet, nearest due date first. */
export function DueSoonPage({ classroom, assignments = [] }) {
    const notSubmitted = assignments
        .filter((assignment) => !assignment.submitted)
        .sort((a, b) => (a.due_at && b.due_at ? new Date(a.due_at) - new Date(b.due_at) : 0));

    if (!notSubmitted.length) return <div><PageHeader eyebrow="THIS TERM" title="Due work" description="Stay on top of your upcoming homework." /><div className="mt-6"><EmptyState title="You're all caught up" description="No homework is due right now." /></div></div>;

    const overdueCount = notSubmitted.filter((assignment) => assignment.due_at && new Date(assignment.due_at) < new Date()).length;
    const [featured, ...rest] = notSubmitted;

    return <div className="grid gap-6">
        <div className="flex flex-wrap items-end justify-between gap-5">
            <PageHeader eyebrow="THIS TERM" title="Due work" description="Stay on top of your upcoming homework." />
            <PageSummary items={[{ label: 'Due soon', value: notSubmitted.length }, { label: 'Overdue', value: overdueCount }]} />
        </div>
        <PriorityAssignmentCard assignment={featured} classroom={classroom} />
        {rest.length > 0 && <section aria-labelledby="upcoming-heading">
            <p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">UPCOMING</p>
            <h2 id="upcoming-heading" className="mt-2 text-2xl font-semibold tracking-tight">Plan what is next</h2>
            <div className="mt-4 grid gap-3">{rest.map((assignment) => <DueWorkCard key={assignment.id} assignment={assignment} classroom={classroom} />)}</div>
        </section>}
    </div>;
}

function PageHeader({ eyebrow, title, description }) {
    return <div><p className="text-[11px] font-bold tracking-[0.16em] text-auth-green">{eyebrow}</p><h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h1><p className="mt-3 text-sm text-auth-muted">{description}</p></div>;
}

function isOverdue(dueAt) {
    return dueAt && new Date(dueAt) < new Date();
}

function PriorityAssignmentCard({ assignment, classroom }) {
    return <article className="overflow-hidden rounded-[1.75rem] border border-auth-green/10 bg-linear-to-br from-[#fff2a8] via-[#f8f5d8] to-auth-soft shadow-xs"><div className="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_15rem] lg:p-8"><div><div className="flex flex-wrap items-center gap-2"><span className={`rounded-full px-3 py-1.5 text-[11px] font-bold tracking-[0.12em] ${isOverdue(assignment.due_at) ? dueStyles.overdue : 'bg-white/70 text-auth-green'}`}>{assignment.due_at ? new Date(assignment.due_at).toLocaleString() : 'No due date'}</span>{classroom && <span className="rounded-full bg-white/60 px-3 py-1.5 text-xs font-semibold text-auth-ink">{classroom.subject}</span>}</div><h2 className="mt-5 text-2xl font-semibold tracking-tight sm:text-3xl">{assignment.title}</h2><p className="mt-2 font-medium text-auth-green">{assignment.skill_name}{assignment.module_name ? ` · ${assignment.module_name}` : ''}</p><p className="mt-4 max-w-xl text-sm leading-6 text-auth-muted">{assignment.question_body}</p>{classroom?.teacher && <div className="mt-5 flex flex-wrap gap-2"><span className="rounded-full bg-white/65 px-3 py-1.5 text-xs text-auth-ink">{classroom.teacher}</span></div>}</div><div className="rounded-2xl border border-white/80 bg-white/65 p-5"><p className="text-[11px] font-bold tracking-[0.14em] text-auth-muted">DUE</p><p className="mt-2 text-xl font-semibold">{assignment.due_at ? new Date(assignment.due_at).toLocaleDateString() : '—'}</p><button type="button" onClick={() => router.visit('/student/homework/submit')} className={`${primaryAction} mt-6 w-full`}>Continue work <Icon className="size-4" /></button></div></div></article>;
}

export function DueWorkCard({ assignment, classroom }) {
    const overdue = isOverdue(assignment.due_at);
    return <article className="grid gap-4 rounded-2xl border border-auth-border bg-white p-4 shadow-xs transition-colors hover:border-auth-green/25 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-5"><span className="flex size-11 items-center justify-center rounded-xl bg-auth-soft text-auth-green"><Icon name="science" /></span><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${overdue ? dueStyles.overdue : dueStyles.upcoming}`}>{assignment.due_at ? new Date(assignment.due_at).toLocaleDateString() : 'No due date'}</span>{classroom && <span className="text-xs text-auth-muted">{classroom.subject}</span>}</div><h3 className="mt-2 font-semibold">{assignment.title}</h3><p className="mt-1 text-sm text-auth-muted">{assignment.skill_name}{assignment.module_name ? ` · ${assignment.module_name}` : ''}</p></div><button type="button" onClick={() => router.visit('/student/homework/submit')} className={`${primaryAction} w-full sm:w-auto`}>Continue <Icon className="size-4" /></button></article>;
}
