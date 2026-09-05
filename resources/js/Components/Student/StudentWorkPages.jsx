import { useMemo, useState } from 'react';
import { EmptyState, Icon } from './DashboardComponents';
import { dueSoonSummary, markedWorks, markedWorkSummary, nextAssignment, upcomingAssignments } from './studentWorkData';

const actionClass = 'auth-focus inline-flex items-center justify-center gap-2 rounded-xl bg-auth-green px-4 py-2.5 text-sm font-semibold text-white hover:bg-auth-green/90';
const secondaryActionClass = 'auth-focus inline-flex items-center justify-center gap-2 rounded-xl border border-auth-green/25 bg-white px-4 py-2.5 text-sm font-semibold text-auth-green hover:bg-auth-soft/60';
const fieldClass = 'auth-focus min-h-11 w-full rounded-xl border border-auth-border bg-white px-3 text-sm text-auth-ink shadow-xs placeholder:text-auth-muted/70';

const statusStyles = {
    excellent: 'bg-auth-soft text-auth-green',
    progress: 'bg-[#eef4df] text-[#556b22]',
    practice: 'bg-amber-50 text-amber-800',
    review: 'bg-orange-50 text-orange-800',
};

const deadlineStyles = {
    urgent: 'bg-orange-50 text-orange-800',
    soon: 'bg-amber-50 text-amber-800',
    week: 'bg-[#f4f2cc] text-[#68601e]',
    upcoming: 'bg-auth-soft text-auth-green',
};

export function StatusBadge({ children, tone = 'excellent' }) {
    return <span className={`inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[tone] || deadlineStyles[tone] || statusStyles.excellent}`}>{children}</span>;
}

export function PageSummary({ items }) {
    return <dl className="grid grid-cols-3 gap-2 sm:gap-3">{items.map((item) => <div key={item.label} className="rounded-2xl border border-auth-border bg-white p-3 shadow-xs sm:p-4"><dt className="text-[11px] leading-tight text-auth-muted sm:text-xs">{item.label}</dt><dd className="mt-2 text-2xl font-semibold tracking-tight text-auth-green sm:text-3xl">{item.value}</dd></div>)}</dl>;
}

export function WorkPageLoadingState() {
    return <div aria-label="Loading assignments" className="grid gap-4">{[1, 2, 3].map((item) => <div key={item} className="animate-pulse rounded-3xl border border-auth-border bg-white p-6"><div className="h-4 w-28 rounded bg-auth-page" /><div className="mt-4 h-6 w-64 max-w-full rounded bg-auth-page" /><div className="mt-6 h-16 rounded-2xl bg-auth-page" /></div>)}</div>;
}

export function MarkedWorkPage({ items = markedWorks, summary = markedWorkSummary }) {
    const [search, setSearch] = useState('');
    const [subject, setSubject] = useState('all');
    const [result, setResult] = useState('all');
    const [sort, setSort] = useState('recent');
    const subjects = [...new Set(items.map((item) => item.subject))];
    const visibleItems = useMemo(() => items.filter((item) => {
        const matchesSearch = `${item.title} ${item.topic}`.toLowerCase().includes(search.toLowerCase());
        return matchesSearch && (subject === 'all' || item.subject === subject) && (result === 'all' || item.statusTone === result);
    }).sort((first, second) => sort === 'score' ? second.percentage - first.percentage : second.releasedOrder - first.releasedOrder), [items, result, search, sort, subject]);

    return <div className="grid gap-6">
        <PageSummary items={summary} />
        <section aria-label="Filter marked work" className="rounded-3xl border border-auth-border bg-white p-4 shadow-xs sm:p-5">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1.5fr)_1fr_1fr_1fr]">
                <label className="grid gap-2 text-xs font-medium text-auth-muted"><span>Search</span><span className="relative"><Icon name="search" className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2" /><input value={search} onChange={(event) => setSearch(event.target.value)} className={`${fieldClass} pl-9`} placeholder="Search homework..." /></span></label>
                <FilterSelect label="Subject" value={subject} onChange={setSubject}><option value="all">All Subjects</option>{subjects.map((item) => <option key={item}>{item}</option>)}</FilterSelect>
                <FilterSelect label="Result" value={result} onChange={setResult}><option value="all">All Results</option><option value="excellent">Excellent</option><option value="progress">Good Progress</option><option value="practice">Needs Practice</option><option value="review">Review Required</option></FilterSelect>
                <FilterSelect label="Sort" value={sort} onChange={setSort}><option value="recent">Recently Released</option><option value="score">Highest Score</option></FilterSelect>
            </div>
        </section>
        <section aria-labelledby="marked-work-list-heading"><h2 id="marked-work-list-heading" className="sr-only">Released homework</h2>{visibleItems.length ? <div className="grid gap-4">{visibleItems.map((work) => <MarkedWorkCard key={work.id} work={work} />)}</div> : <EmptyState title="No marked work yet" description="Your teacher's released feedback will appear here." />}</section>
    </div>;
}

function FilterSelect({ label, value, onChange, children }) {
    return <label className="grid gap-2 text-xs font-medium text-auth-muted"><span>{label}</span><select value={value} onChange={(event) => onChange(event.target.value)} className={fieldClass}>{children}</select></label>;
}

function MarkedWorkCard({ work }) {
    return <article className="rounded-3xl border border-auth-border bg-white p-5 shadow-xs sm:p-6">
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(12rem,.85fr)_10rem] lg:items-center">
            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2 text-xs text-auth-muted"><span className="font-semibold text-auth-green">{work.subject}</span><span aria-hidden="true">•</span><span>Released {work.released}</span><span aria-hidden="true">•</span><span>{work.teacher}</span></div><h3 className="mt-3 text-xl font-semibold tracking-tight">{work.title}</h3><p className="mt-1 text-sm font-medium text-auth-muted">{work.topic}</p><blockquote className="mt-5 border-l-2 border-auth-green/25 pl-4 text-sm leading-6 text-auth-muted"><span className="font-medium text-auth-ink">Teacher feedback: </span>{work.feedback}</blockquote></div>
            <div><p className="text-[11px] font-semibold tracking-[0.16em] text-auth-muted">LEARNING AREAS</p><ul className="mt-3 flex flex-wrap gap-2">{work.learningAreas.map((area) => <li key={area} className="rounded-full border border-auth-border bg-auth-page px-3 py-1.5 text-xs text-auth-ink">{area}</li>)}</ul></div>
            <div className="flex flex-row items-center justify-between gap-4 border-t border-auth-border pt-5 lg:flex-col lg:items-stretch lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0"><div><p className="text-3xl font-semibold tracking-tight text-auth-green">{work.score}<span className="text-base font-normal text-auth-muted"> / {work.total}</span></p><p className="mt-1 text-xs text-auth-muted">{work.percentage}% score</p><div className="mt-3"><StatusBadge tone={work.statusTone}>{work.status}</StatusBadge></div></div>{/* TODO BACKEND: Connect to marked homework detail route. */}<a href="#" className={actionClass}>View <span className="hidden sm:inline">Marked Work</span><Icon className="size-4" /></a></div>
        </div>
    </article>;
}

export function DueSoonPage({ featured = nextAssignment, assignments = upcomingAssignments, summary = dueSoonSummary }) {
    return <div className="grid gap-6"><PageSummary items={summary} />{featured ? <NextAssignmentCard assignment={featured} /> : <EmptyState title="You're all caught up!" description="No assignments are due soon." />}<section aria-labelledby="upcoming-heading"><div className="mb-4 flex items-end justify-between gap-3"><div><p className="text-[11px] font-semibold tracking-[0.16em] text-auth-green">PLAN AHEAD</p><h2 id="upcoming-heading" className="mt-2 text-xl font-semibold tracking-tight">Upcoming Assignments</h2></div><span className="text-xs text-auth-muted">{assignments.length} assignments</span></div>{assignments.length ? <div className="grid gap-3">{assignments.map((assignment) => <UpcomingAssignmentCard key={assignment.id} assignment={assignment} />)}</div> : <EmptyState title="You're all caught up!" description="No assignments are due soon." />}</section></div>;
}

function NextAssignmentCard({ assignment }) {
    return <article className="overflow-hidden rounded-3xl border border-auth-green/15 bg-linear-to-br from-[#f4f2cc] to-auth-soft shadow-xs"><div className="grid gap-7 p-6 lg:grid-cols-[minmax(0,1.3fr)_minmax(17rem,.7fr)] lg:p-8"><div><div className="flex flex-wrap items-center gap-2"><p className="text-[11px] font-semibold tracking-[0.18em] text-auth-green">NEXT ASSIGNMENT</p><StatusBadge tone="soon">Due Soon</StatusBadge></div><h2 className="mt-4 text-2xl font-semibold tracking-tight sm:text-3xl">{assignment.title}</h2><p className="mt-1 font-medium text-auth-green">{assignment.topic}</p><p className="mt-4 max-w-2xl text-sm leading-6 text-auth-muted">{assignment.description}</p><p className="mt-4 text-xs text-auth-muted">{assignment.subject} · Teacher: {assignment.teacher}</p></div><div className="rounded-2xl border border-white/70 bg-white/65 p-5"><p className="text-xs text-auth-muted">Due</p><p className="mt-1 text-lg font-semibold">{assignment.due}</p><p className="mt-4 text-xs text-auth-muted">Time remaining</p><p className="mt-1 font-semibold text-auth-green">{assignment.timeRemaining}</p><div className="mt-5 flex items-center justify-between border-t border-auth-green/10 pt-4 text-xs"><span className="text-auth-muted">Progress</span><span className="font-semibold">{assignment.status}</span></div></div></div><div className="flex flex-col gap-3 border-t border-auth-green/10 bg-white/35 px-6 py-4 sm:flex-row sm:justify-end lg:px-8">{/* TODO BACKEND: Connect assignment detail URL. */}<a href="#" className={secondaryActionClass}>View Assignment</a>{/* TODO BACKEND: Connect to the existing assignment submission destination. */}<a href="#submit" className={actionClass}><Icon name="submit" className="size-4" />Submit Homework</a></div></article>;
}

function UpcomingAssignmentCard({ assignment }) {
    return <article className="grid gap-4 rounded-2xl border border-auth-border bg-white p-4 shadow-xs sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-5"><span className="flex size-11 items-center justify-center rounded-xl bg-auth-soft text-auth-green"><Icon name={assignment.subject === 'Mathematics' ? 'calculator' : 'science'} /></span><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold">{assignment.title}</h3><StatusBadge tone={assignment.deadlineTone}>{assignment.deadline}</StatusBadge></div><p className="mt-1 text-sm text-auth-muted">{assignment.topic} · {assignment.subject}</p><div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-auth-muted"><span>Due {assignment.due}</span><span className="font-medium text-auth-ink">{assignment.timeRemaining}</span><span>{assignment.status}</span></div></div>{/* TODO BACKEND: Connect assignment detail URL. */}<a href="#" className={`${secondaryActionClass} w-full sm:w-auto`}>View Assignment <Icon className="size-4" /></a></article>;
}
