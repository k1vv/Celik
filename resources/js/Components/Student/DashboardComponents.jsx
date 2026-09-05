export function Icon({ name = 'arrow', className = 'size-5' }) {
    const paths = {
        overview: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
        submit: 'M12 16V3m-5 5 5-5 5 5M4 15v6h16v-6',
        progress: 'M4 20V10m8 10V4m8 16v-8',
        work: 'M5 3h14v18H5zM9 8h6M9 12h6M9 16h4',
        practice: 'm5 19 14-14M5 5h14v14',
        due: 'M4 5h16v16H4zM8 3v4m8-4v4M4 11h16',
        settings: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M12 2v3m0 14v3M2 12h3m14 0h3M5 5l2 2m10 10 2 2M5 19l2-2M17 7l2-2',
        logout: 'M9 3H4v18h5m5-14 5 5-5 5M8 12h11',
        arrow: 'M5 12h14m-5-5 5 5-5 5',
        menu: 'M4 6h16M4 12h16M4 18h16',
        close: 'm6 6 12 12M6 18 18 6',
    };
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name] || paths.arrow} /></svg>;
}

export function DashboardCard({ title, action, children, className = '' }) {
    return <section className={`min-w-0 rounded-3xl border border-auth-border bg-white p-5 shadow-xs sm:p-6 ${className}`}>
        {title && <div className="mb-6 flex items-center justify-between gap-3"><h2 className="text-lg font-semibold tracking-tight">{title}</h2>{action}</div>}
        {children}
    </section>;
}

export function EmptyState({ title, description }) {
    return <div className="rounded-2xl bg-auth-page p-6 text-sm"><p className="font-medium">{title}</p>{description && <p className="mt-2 text-auth-muted">{description}</p>}</div>;
}

export function ProgressBar({ completed, total, label, cleared = false }) {
    const percent = total > 0 ? Math.min(100, Math.max(0, completed / total * 100)) : 0;
    return <div role="progressbar" aria-label={label} aria-valuenow={Math.round(percent)} aria-valuemin={0} aria-valuemax={100} className="h-1.5 overflow-hidden rounded-full bg-black/5"><div className={`h-full rounded-full ${cleared ? 'bg-auth-green' : 'bg-[#c3b54b]'}`} style={{ width: `${percent}%` }} /></div>;
}

export function LearningGapItem({ gap, onPractice }) {
    return <div className="rounded-2xl border border-auth-border p-4">
        <div className="flex items-start gap-3"><span className={`rounded-lg px-2 py-1 text-xs font-semibold ${gap.cleared ? 'bg-auth-soft text-auth-green' : 'bg-amber-50 text-amber-800'}`}>{gap.code}</span><div className="min-w-0 flex-1"><h3 className="text-sm font-semibold">{gap.title}</h3><p className="mt-2 text-xs text-auth-muted">Standard {gap.standard}</p></div><button type="button" onClick={onPractice} aria-label={`${gap.cleared ? 'Review' : 'Practice'} ${gap.code}`} className="auth-focus rounded-full border border-auth-border p-2 text-auth-green hover:bg-auth-soft"><Icon className="size-4" /></button></div>
        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs"><span className={`rounded-full px-2 py-1 ${gap.cleared ? 'bg-auth-soft text-auth-green' : 'bg-amber-50 text-amber-800'}`}>{gap.cleared ? 'Cleared' : 'Practice open'}</span><span className="text-auth-muted">{gap.source}</span></div>
        <div className="mt-4 flex items-center gap-4"><div className="flex-1"><ProgressBar completed={gap.completed} total={gap.total} cleared={gap.cleared} label={`${gap.code} questions completed`} /></div><span className="text-xs text-auth-muted">{gap.completed} of {gap.total} questions completed</span></div>
    </div>;
}

export function HomeworkChart({ items }) {
    if (!items.length) return <EmptyState title="Your progress starts here." description="Cleared learning gaps will appear after each homework." />;
    const maximum = Math.max(3, ...items.map((item) => item.value));
    return <><div className="flex h-48 items-end gap-3 border-b border-auth-border pt-4" role="img" aria-label={items.map((item) => `${item.label}: ${item.value} gaps cleared`).join(', ')}>{items.map((item) => <div key={item.label} className="flex h-full min-w-0 flex-1 flex-col justify-end gap-2 text-center"><span className="text-xs font-medium text-auth-green">{item.value}</span><div className="mx-auto w-full max-w-10 rounded-t-lg bg-linear-to-t from-auth-green/80 to-auth-lime" style={{ height: `${item.value / maximum * 75}%`, minHeight: item.value ? 4 : 0 }} /><span className="pb-3 text-[11px] text-auth-muted">{item.label}</span></div>)}</div><p className="mt-4 text-xs leading-relaxed text-auth-muted">Each bar represents a learning gap you successfully cleared.</p></>;
}
