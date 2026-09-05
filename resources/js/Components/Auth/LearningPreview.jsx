const progressSkills = [
    { name: 'Fractions', value: '82%', width: 'w-[82%]' },
    { name: 'Algebra', value: '68%', width: 'w-[68%]' },
    { name: 'Geometry', value: '91%', width: 'w-[91%]' },
    { name: 'Decimals', value: '76%', width: 'w-[76%]' },
];

const previewMeta = {
    practice: ['CELIK / ADAPTIVE PRACTICE', 'Question 4 of 8'], feedback: ['CELIK / HOMEWORK REVIEW', 'Marked work'], personalised: ['CELIK / YOUR NEXT STEP', 'This week'], progress: ['CELIK / LEARNING PROGRESS', 'This week'], teacher: ['CELIK / CLASS INSIGHT', 'Form 2'],
};

export default function LearningPreview({ type }) {
    const previews = { practice: <PracticePreview />, feedback: <FeedbackPreview />, personalised: <PersonalisedPreview />, progress: <ProgressPreview />, teacher: <TeacherInsightPreview /> };
    const [label, status] = previewMeta[type] ?? previewMeta.practice;

    return <div aria-hidden="true" className="auth-preview auth-preview-enter w-full max-w-[350px] rounded-[24px] border border-white/80 bg-white/65 p-3 shadow-xl shadow-auth-green/5"><div className="auth-preview-inner rounded-2xl border border-auth-border bg-white p-5"><div className="mb-4 flex items-center justify-between gap-2 text-[10px] font-semibold tracking-wider text-auth-green"><span>{label}</span><span className="rounded-full bg-auth-page px-2 py-1 font-normal tracking-normal text-auth-muted">{status}</span></div>{previews[type] ?? previews.practice}</div></div>;
}

function PracticePreview() {
    return <><p className="text-xs text-auth-muted">MATHEMATICS · FRACTIONS</p><h3 className="my-4 text-3xl font-medium tracking-tight">¾ + ½ = ?</h3><div className="space-y-2">{['4/6', '5/4', '3/8'].map((answer, index) => <div key={answer} className={`auth-preview-reveal flex items-center gap-3 rounded-lg border px-3 py-2 text-sm ${index === 1 ? 'border-auth-green/25 bg-auth-soft text-auth-green' : 'border-auth-border'}`} style={{ animationDelay: `${120 + index * 90}ms` }}><span>{index === 1 ? '●' : '○'}</span>{answer}</div>)}</div><PreviewFooter><span>Adaptive difficulty</span><span>● ● ● ○ ○</span></PreviewFooter><p className="mt-3 text-[10px] text-auth-muted">Skill: Adding unlike fractions · Developing</p></>;
}

function FeedbackPreview() {
    return <><div className="flex items-start justify-between gap-3"><div><p className="text-xs text-auth-muted">HOMEWORK · ALGEBRA</p><h3 className="mt-2 text-lg font-semibold">Solve: 2x + 6 = 14</h3></div><span className="auth-preview-reveal rounded-lg bg-auth-soft px-2 py-1 text-xs font-semibold text-auth-green">8 / 10</span></div><div className="auth-preview-reveal mt-4 rounded-xl border border-auth-border bg-auth-page/60 p-3 text-sm"><p className="text-[10px] font-semibold tracking-wider text-auth-muted">STUDENT ANSWER</p><p className="mt-2 font-medium">2x = 8</p><p className="font-medium">x = 4</p></div><div className="mt-3 space-y-1.5 text-xs text-auth-green"><p className="auth-preview-reveal">✓ Correct method</p><p className="auth-preview-reveal" style={{ animationDelay: '120ms' }}>✓ Correct answer</p></div><div className="auth-preview-reveal mt-4 rounded-xl bg-auth-soft/70 p-3"><p className="text-[10px] font-semibold text-auth-green">TEACHER FEEDBACK</p><p className="mt-1 text-xs leading-relaxed text-auth-ink">Good work. You correctly isolated x by subtracting 6 from both sides.</p></div></>;
}

function PersonalisedPreview() {
    const recommendations = ['Simplifying fractions', 'Equivalent fractions', 'Comparing fractions'];
    return <><p className="text-xs text-auth-muted">PERSONALISED PRACTICE</p><div className="auth-preview-reveal mt-3 rounded-xl bg-auth-soft/70 p-3"><p className="text-[10px] font-semibold tracking-wider text-auth-green">FOCUS AREA</p><h3 className="mt-1 text-lg font-semibold">Fraction equivalence</h3><p className="mt-1 text-[10px] text-auth-muted">Based on your latest homework</p></div><p className="mt-4 text-[10px] font-semibold tracking-wider text-auth-muted">RECOMMENDED NEXT</p><div className="mt-2 space-y-2">{recommendations.map((item, index) => <div key={item} className="auth-preview-reveal flex items-center gap-3 rounded-lg border border-auth-border px-3 py-2" style={{ animationDelay: `${120 + index * 80}ms` }}><span className="text-xs font-semibold text-auth-green">0{index + 1}</span><span className="text-xs font-medium">{item}</span></div>)}</div><div className="mt-4"><div className="flex justify-between text-xs"><span>Current mastery</span><span className="font-semibold text-auth-green">72%</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-auth-page"><div className="auth-progress-fill h-full w-[72%] rounded-full bg-auth-lime" /></div></div></>;
}

function ProgressPreview() {
    return <><div className="flex items-start justify-between gap-3"><div><p className="text-xs text-auth-muted">YOUR PROGRESS</p><h3 className="mt-1 text-lg font-semibold">Mathematics</h3></div><Sparkline /></div><div className="mt-4 space-y-3">{progressSkills.map((skill, index) => <div key={skill.name} className="auth-preview-reveal" style={{ animationDelay: `${100 + index * 70}ms` }}><div className="mb-1.5 flex justify-between text-xs"><span>{skill.name}</span><span className="font-medium text-auth-green">{skill.value}</span></div><div className="h-1.5 overflow-hidden rounded-full bg-auth-page"><div className={`auth-progress-fill h-full rounded-full bg-auth-green/75 ${skill.width}`} /></div></div>)}</div><div className="mt-4 flex items-center gap-2 rounded-lg bg-auth-soft/70 px-3 py-2 text-xs font-medium text-auth-green"><span className="text-base leading-none">↑</span> 12% improvement this week</div></>;
}

function TeacherInsightPreview() {
    const gaps = [['Fractions', '8 students'], ['Linear equations', '5 students'], ['Ratio', '3 students']];
    return <><p className="text-xs text-auth-muted">MATHEMATICS · FORM 2</p><div className="mt-2 flex items-end justify-between"><h3 className="text-lg font-semibold">24 students</h3><span className="text-[10px] text-auth-muted">Updated today</span></div><p className="mt-4 text-[10px] font-semibold tracking-wider text-auth-muted">NEEDS ATTENTION</p><div className="mt-2 space-y-2">{gaps.map(([skill, count], index) => <div key={skill} className="auth-preview-reveal flex items-center justify-between rounded-lg bg-auth-page px-3 py-2.5" style={{ animationDelay: `${100 + index * 90}ms` }}><span className="text-xs font-medium">{skill}</span><span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-800">{count}</span></div>)}</div><div className="auth-preview-reveal mt-4 rounded-xl border border-auth-green/15 bg-auth-soft/70 p-3"><p className="text-[10px] font-semibold tracking-wider text-auth-green">PRIORITY TODAY</p><p className="mt-1 text-sm font-semibold">Equivalent fractions</p><p className="mt-1 text-[10px] text-auth-muted">8 students need support</p></div></>;
}

function Sparkline() {
    return <svg viewBox="0 0 72 34" className="mt-1 h-9 w-18 overflow-visible" fill="none"><path d="M2 27 16 21 28 23 41 13 54 16 70 5" stroke="currentColor" strokeWidth="2" className="text-auth-green" /><path d="M2 31H70" stroke="currentColor" strokeWidth="1" className="text-auth-border" strokeDasharray="3 3" /><circle cx="70" cy="5" r="3" className="fill-auth-lime stroke-auth-green" strokeWidth="2" /></svg>;
}

function PreviewFooter({ children }) {
    return <div className="mt-4 flex justify-between gap-2 border-t border-auth-border pt-3 text-xs font-medium text-auth-green">{children}</div>;
}
