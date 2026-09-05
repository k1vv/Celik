import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import StudentSidebar, { studentNavigation } from './StudentSidebar';
import { DashboardCard, EmptyState, HomeworkChart, Icon, LearningGapItem, ProgressBar } from './DashboardComponents';
import { mockDashboardStats, mockHomeworkProgress, mockLearningGaps, mockNextDue, mockNextPractice, mockRecentWork, mockStudent } from './dashboardData';
import { DueSoonPage, MarkedWorkPage } from './StudentWorkPages';

const textAction = 'auth-focus inline-flex items-center gap-2 rounded-lg text-xs font-semibold text-auth-green';
const primaryAction = 'auth-focus inline-flex items-center justify-center gap-3 rounded-xl bg-auth-green px-5 py-3 text-sm font-medium text-white hover:bg-auth-green/90';

export default function StudentDashboard({ classroom, children, dashboardData = {}, initialView = 'overview' }) {
    const { auth, flash } = usePage().props;
    const [active, setActive] = useState(() => flash?.result ? 'practice' : initialView);
    useEffect(() => {
        const restore = () => {
            const view = window.location.hash.slice(1);
            setActive([...studentNavigation.map((item) => item.id), 'settings'].includes(view) ? view : flash?.result ? 'practice' : initialView);
        };
        restore();
        window.addEventListener('hashchange', restore);
        return () => window.removeEventListener('hashchange', restore);
    }, [flash?.result, initialView]);
    function navigate(view) {
        const routes = {
            overview: '/student/dashboard',
            submit: '/student/homework/submit',
            practice: '/practice',
        };

        router.visit(routes[view] || `/student/dashboard#${view}`);
    }
    // TODO: Replace sample dashboard props independently as backend summaries become available.
    const { stats = mockDashboardStats, learningGaps = mockLearningGaps, homeworkProgress = mockHomeworkProgress, recentWork = mockRecentWork, nextPractice = mockNextPractice, nextDue = mockNextDue } = dashboardData;
    const student = { ...mockStudent, name: auth?.user?.name || mockStudent.name, classroom: classroom?.name || mockStudent.classroom, subject: classroom?.subject || mockStudent.subject };
    const overview = active === 'overview';
    const remaining = Math.max(0, stats.totalGaps - stats.gapsCleared);
    const pageDescriptions = {
        work: 'Review your released homework, teacher feedback, and areas to improve.',
        due: 'Stay on top of your upcoming assignments and submission deadlines.',
    };

    return <div className="min-h-dvh bg-auth-page text-auth-ink md:pl-60">
        <Head title={studentNavigation.find((item) => item.id === active)?.label || 'Settings'} />
        <div className="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8 xl:p-10">
            <StudentSidebar active={active} navigate={navigate} student={student} nextDue={nextDue} />
            <main className="min-w-0">
                {!['work', 'due', 'practice'].includes(active) && <div className="mb-7 flex flex-wrap items-end justify-between gap-3"><div><p className="mb-2 text-xs font-medium text-auth-muted">YOUR LEARNING SPACE</p><h1 className="text-3xl font-semibold tracking-tight">{overview ? 'A little progress, every day.' : studentNavigation.find((item) => item.id === active)?.label || 'Settings'}</h1>{pageDescriptions[active] && <p className="mt-3 max-w-2xl text-sm leading-6 text-auth-muted">{pageDescriptions[active]}</p>}</div>{active !== 'practice' && <span className="rounded-full border border-auth-border bg-white px-3 py-1 text-[11px] text-auth-muted">Sample learning data</span>}</div>}
                {overview && <section className="mb-6 grid gap-8 rounded-3xl border border-auth-green/10 bg-linear-to-br from-[#f4f2cc] to-auth-soft p-6 lg:grid-cols-[1.3fr_1fr] lg:items-center lg:p-8"><div><p className="text-xs font-medium text-auth-green">{student.subject} · {mockStudent.form} · {mockStudent.curriculum}</p><h2 className="mt-4 max-w-md text-3xl font-semibold leading-tight tracking-tight">{remaining ? `${remaining === 2 ? 'Two' : remaining} learning gaps left to clear` : "You're all caught up."}</h2><p className="mt-3 max-w-lg text-sm leading-relaxed text-auth-muted">You've cleared {stats.gapsCleared} of {stats.totalGaps} learning gaps confirmed by your teacher this term.{remaining > 0 && ' Keep going — your next practice is waiting.'}</p></div><dl className="grid grid-cols-3 gap-3">{[{ value: stats.submitted, label: 'Submitted' }, { value: stats.gapsCleared, label: 'Gaps Cleared' }, { value: stats.practiceWaiting, label: 'Practice Waiting' }].map((stat) => <div key={stat.label} className="rounded-2xl border border-white/60 bg-white/50 px-2 py-5 text-center"><dd className="text-3xl font-semibold tracking-tight text-auth-green">{stat.value}</dd><dt className="mt-2 text-xs text-auth-muted">{stat.label}</dt></div>)}</dl></section>}
                {(overview || active === 'progress') && <div className="grid gap-6 xl:grid-cols-[1.45fr_1fr]"><DashboardCard title="Your open learning gaps" action={<span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs text-amber-800">{learningGaps.filter((gap) => !gap.cleared).length} open</span>}><div className="space-y-3">{learningGaps.length ? learningGaps.map((gap) => <LearningGapItem key={gap.code} gap={gap} onPractice={() => navigate('practice')} />) : <EmptyState title="You're all caught up." description="No active learning gaps have been identified." />}</div></DashboardCard><div className="grid content-start gap-6"><DashboardCard title="Gaps cleared per homework"><HomeworkChart items={homeworkProgress} /></DashboardCard>{overview && <DashboardCard title="Recently released" action={<button onClick={() => navigate('work')} className={textAction}>See all <Icon className="size-3" /></button>}><RecentWork items={recentWork} navigate={navigate} /></DashboardCard>}</div></div>}
                {overview && <DashboardCard className="mt-6 bg-linear-to-r from-[#f4f2cc] to-auth-soft">{nextPractice ? <div className="grid items-center gap-6 lg:grid-cols-[1.5fr_1fr]"><div><p className="text-[11px] font-semibold tracking-widest text-auth-green">YOUR NEXT PRACTICE · {nextPractice.code}</p><h2 className="mt-3 text-2xl font-semibold tracking-tight">{nextPractice.title}</h2><p className="mt-3 max-w-2xl text-sm leading-relaxed text-auth-muted">{nextPractice.description}</p></div><div><div className="mb-3 flex justify-between text-xs text-auth-muted"><span>{nextPractice.questions} Questions · ~{nextPractice.minutes} min</span><span>{nextPractice.completed}/{nextPractice.questions} complete</span></div><ProgressBar completed={nextPractice.completed} total={nextPractice.questions} label="Next practice progress" /><button className={`${primaryAction} mt-5 w-full`} onClick={() => navigate('practice')}>Start Practice <Icon className="size-4" /></button><p className="mt-2 text-center text-[11px] text-auth-muted">Opens your available class practice.</p></div></div> : <EmptyState title="No personalised practice is waiting right now." />}</DashboardCard>}
                {active === 'practice' && children}
                {active === 'work' && <MarkedWorkPage />}
                {active === 'due' && <DueSoonPage />}
                {active === 'submit' && children}
                {active === 'settings' && <DashboardCard title="Your account"><p className="font-semibold">{student.name}</p><p className="mt-2 text-sm text-auth-muted">{student.classroom}</p><p className="mt-5 text-sm text-auth-muted">Account settings are not available yet. Contact your teacher to update your details.</p></DashboardCard>}
            </main>
        </div>
    </div>;
}

function RecentWork({ items, navigate, expanded = false }) {
    if (!items.length) return <EmptyState title="No marked work has been released yet." />;
    return <div className="divide-y divide-auth-border">{items.map((work) => <div key={work.id} className="flex items-center gap-3 py-4 first:pt-0 last:pb-0"><div className="shrink-0 rounded-xl bg-auth-page px-3 py-3 text-sm font-semibold text-auth-green">{work.mark}<span className="text-xs font-normal text-auth-muted"> / {work.maximum}</span></div><div className="min-w-0 flex-1"><h3 className="text-sm font-medium leading-relaxed">{work.title}</h3><p className="mt-1 text-xs text-auth-muted">{work.date} · {work.gap} tagged</p>{expanded && <p className="mt-2 text-xs text-auth-muted">Sample result. Teacher feedback is not connected yet.</p>}</div>{!expanded && <button onClick={() => navigate('work')} aria-label={`View ${work.title}`} className="auth-focus rounded-full p-2 text-auth-green"><Icon className="size-4" /></button>}</div>)}</div>;
}
