import { Link } from '@inertiajs/react';

function statusOf(submission) {
    if (submission.extraction_status === 'failed') {
        return { label: 'Could not be read', style: 'bg-rose-100 text-rose-800' };
    }

    if (submission.extraction_status === 'pending') {
        return { label: 'Being graded', style: 'bg-slate-100 text-slate-600' };
    }

    if (submission.teacher_validated_at) {
        return { label: 'Checked', style: 'bg-emerald-100 text-emerald-800' };
    }

    if (submission.needs_review_count > 0) {
        return { label: `${submission.needs_review_count} to review`, style: 'bg-amber-100 text-amber-800' };
    }

    return { label: 'Not checked yet', style: 'bg-slate-100 text-slate-600' };
}

export default function SubmissionList({ submissions }) {
    return (
        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 className="text-sm font-semibold text-slate-800">PDF submissions</h2>

            {submissions.length === 0 && (
                <p className="mt-1 text-xs text-slate-500">No PDF submissions yet. They appear here once students hand in homework.</p>
            )}

            {submissions.length > 0 && (
                <ul className="mt-3 max-h-96 divide-y divide-slate-100 overflow-y-auto">
                    {submissions.map((submission) => (
                        <SubmissionItem key={submission.id} submission={submission} />
                    ))}
                </ul>
            )}
        </div>
    );
}

function SubmissionItem({ submission }) {
    const status = statusOf(submission);
    const graded = submission.extraction_status === 'extracted';

    const content = (
        <>
            <span className="min-w-0">
                <span className="block text-sm font-medium text-slate-800">
                    {`${submission.student_name} · ${submission.assignment_title}`}
                </span>
                <span className="block text-xs text-slate-500">
                    {graded && `${submission.answered_count} of ${submission.question_count} answers read · `}
                    {new Date(submission.created_at).toLocaleString()}
                </span>
            </span>
            <span className={`shrink-0 rounded-md px-2 py-1 text-[10px] font-bold ${status.style}`}>{status.label}</span>
        </>
    );

    const layout = 'flex items-center justify-between gap-4 rounded-md px-2 py-2.5';

    return (
        <li>
            {graded ? (
                <Link href={`/teacher/submissions/${submission.id}`} className={`${layout} hover:bg-slate-50`}>
                    {content}
                </Link>
            ) : (
                <div className={layout}>{content}</div>
            )}
        </li>
    );
}
