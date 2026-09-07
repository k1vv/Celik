/**
 * A PDF covers a whole assignment; a photo or typed answer covers one
 * question, so those are only offered when there is exactly one.
 */
export function availableModes(assignment) {
    return assignment.questions.length === 1 ? ['pdf', 'photo', 'typed'] : ['pdf'];
}

/** The request for the selected mode, or null while there is nothing to submit. */
export function homeworkRequest(mode, assignment, { pdf, photo, answer }) {
    if (mode === 'pdf') {
        return pdf ? { url: '/submissions', data: { assignment_id: assignment.id, pdf } } : null;
    }

    if (mode === 'photo') {
        return photo ? { url: '/homework', data: { question_id: assignment.question_id, photo } } : null;
    }

    return answer.trim() ? { url: '/homework', data: { question_id: assignment.question_id, answer } } : null;
}

/** Whether any handed-in PDF is still waiting for its grading job. */
export function isGrading(submissions) {
    return submissions.some((submission) => submission.extraction_status === 'pending');
}
