const previewSkills = [
    { name: 'Fractions', width: 'w-4/5', score: '80%' },
    { name: 'Algebra', width: 'w-3/5', score: '60%' },
    { name: 'Geometry', width: 'w-11/12', score: '92%' },
];

export default function LearningPreview({ type }) {
    const previews = {
        practice: <PracticePreview />,
        dashboard: <ProgressPreview classroom />,
        mastery: <ProgressPreview />,
        gaps: <LearningGapPreview />,
        targeted: <TargetedPracticePreview />,
    };

    return (
        <div aria-hidden="true" className="auth-preview auth-slide w-full max-w-[350px] rounded-[24px] border border-white/80 bg-white/65 p-3 shadow-xl shadow-auth-green/5">
            <div className="auth-preview-inner rounded-2xl border border-auth-border bg-white p-5">
                <div className="mb-3 flex items-center justify-between text-[10px] font-semibold tracking-wider text-auth-green"><span>CELIK / LEARNING IN FOCUS</span><span className="rounded-full bg-auth-page px-2 py-1 font-normal tracking-normal text-auth-muted">Preview</span></div>
                {previews[type] ?? previews.practice}
            </div>
        </div>
    );
}

function PracticePreview() {
    return <>
        <p className="text-xs text-auth-muted">MATHEMATICS · FRACTIONS</p>
        <h3 className="my-4 text-3xl font-medium">¾ + ½ = ?</h3>
        <div className="space-y-2">
            {['4/6', '5/4', '3/8'].map((answer, index) => <div key={answer} className={`flex items-center gap-3 rounded-lg border px-3 py-2 text-sm ${index === 1 ? 'border-auth-green/25 bg-auth-soft text-auth-green' : 'border-auth-border'}`}><span>{index === 1 ? '●' : '○'}</span>{answer}</div>)}
        </div>
        <PreviewFooter><span>Adaptive difficulty</span><span>●●●○○</span></PreviewFooter>
    </>;
}

function ProgressPreview({ classroom = false }) {
    return <>
        <h3 className="text-lg font-semibold">{classroom ? 'Classroom insights' : 'Your skill mastery'}</h3>
        <p className="mb-5 mt-1 text-xs text-auth-muted">{classroom ? '24 students · a shared picture of progress' : 'Every skill is another step forward'}</p>
        <div className="space-y-4">{previewSkills.map((skill) => <div key={skill.name}><div className="mb-2 flex justify-between text-xs"><span>{skill.name}</span><span>{skill.score}</span></div><div className="h-2 rounded-full bg-auth-page"><div className={`h-full rounded-full ${skill.width} ${classroom ? 'bg-auth-green/65' : 'bg-auth-lime'}`} /></div></div>)}</div>
        <PreviewFooter><span>{classroom ? 'Class focus' : 'Focus next'}</span><span>Algebra ↗</span></PreviewFooter>
    </>;
}

function LearningGapPreview() {
    const previewRows = [
        { name: 'Aisyah', levels: ['strong', 'gap', 'strong'] },
        { name: 'Amir', levels: ['strong', 'developing', 'strong'] },
        { name: 'Huda', levels: ['developing', 'gap', 'strong'] },
        { name: 'Daniel', levels: ['strong', 'gap', 'developing'] },
    ];
    const colors = { strong: 'bg-auth-soft', developing: 'bg-amber-100', gap: 'bg-red-200/75' };

    return <>
        <h3 className="mb-4 text-lg font-semibold">A clearer view of learning</h3>
        <div className="grid grid-cols-[1fr_repeat(3,1fr)] items-center gap-2 text-center text-[10px]">
            <span />{['Fractions', 'Algebra', 'Geometry'].map((skill) => <span key={skill} className="text-auth-muted">{skill}</span>)}
            {previewRows.map((row) => <PreviewRow key={row.name} row={row} colors={colors} />)}
        </div>
        <p className="mt-3 text-[10px] text-auth-muted">Green: strong · Amber: developing · Coral: needs support</p>
        <PreviewFooter><span>Class gap detected</span><span>Algebra</span></PreviewFooter>
    </>;
}

function PreviewRow({ row, colors }) {
    return <><span className="text-left">{row.name}</span>{row.levels.map((level, index) => <span key={index} className={`h-6 rounded-md ${colors[level]}`} />)}</>;
}

function TargetedPracticePreview() {
    return <>
        <h3 className="text-lg font-semibold">Your next best step</h3>
        <div className="mt-4 space-y-2">
            {[['01', 'Fractions', 'Current skill'], ['02', 'Mastery 42%', 'Room to grow'], ['03', 'Difficulty 2', 'Matched to your progress'], ['04', 'Next question', 'Keep learning']].map(([step, title, detail]) => <div key={step} className="flex items-center gap-3 rounded-lg bg-auth-page px-3 py-2"><span className="text-xs text-auth-green">{step}</span><div className="flex flex-1 items-center justify-between gap-2"><span className="text-xs font-medium">{title}</span><span className="text-[10px] text-auth-muted">{detail}</span></div></div>)}
        </div>
        <PreviewFooter><span>Small steps. Steady progress.</span></PreviewFooter>
    </>;
}

function PreviewFooter({ children }) {
    return <div className="mt-4 flex justify-between gap-2 border-t border-auth-border pt-3 text-xs font-medium text-auth-green">{children}</div>;
}
