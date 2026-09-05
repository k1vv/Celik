// TODO BACKEND: Replace this detail object with the selected marked work,
// its tagged issues, teacher note, and practice recommendation.
export const markedWorkDetail = {
    releasedDate: '2 Sep', teacher: 'Cikgu Rahimah', questionNumber: 3, score: 7, totalScore: 10, issueCount: 2,
    question: 'Explain why the metal spoon feels colder',
    issues: [
        { code: 'S-04', title: 'Metal is naturally colder than wood', variant: 'fact', description: 'Metal isn’t colder — both spoons are at room temperature. Metal conducts heat away from your hand faster, so it feels colder. Learning standard 4.2.1.' },
        { code: 'A-01', title: 'States without explaining', variant: 'method', description: 'The question asks you to explain. You named a cause but never linked it to what your hand actually feels.' },
    ],
    teacherNote: { teacher: 'Cikgu Rahimah', initials: 'CR', message: 'Good attempt. Re-read the section on conduction — the word “cold” is doing too much work here.' },
    practice: { title: '2 practice sets unlocked from your gaps', description: 'Built from S-04 and A-01 — the two issues tagged on this answer.' },
};

// TODO BACKEND: Fetch upcoming assignments and submission status, then
// calculate deadline and time remaining server-side.
export const dueSoonSummary = [
    { label: 'Due soon', value: '3' },
    { label: 'Due today', value: '1' },
];

export const nextAssignment = {
    id: 7,
    title: 'Homework 7',
    topic: 'Heat and Energy Transfer',
    subject: 'Science',
    due: 'Today', dueTime: '5:00 PM', deadline: 'DUE TODAY', timeRemaining: 'Due in 4 hours', questionCount: 5,
    teacher: 'Cikgu Rahimah', description: 'Complete questions 1–5 and explain your reasoning.', status: 'In Progress',
};

export const upcomingAssignments = [
    { id: 8, title: 'Algebra Practice', subject: 'Mathematics', due: '8 Sep · 11:59 PM', teacher: 'Cikgu Amir', questionCount: 10, status: 'Not Started', progress: 0, deadline: '8 SEP', deadlineTone: 'week' },
    { id: 9, title: 'States of Matter', subject: 'Science', due: '10 Sep · 8:00 AM', teacher: 'Cikgu Rahimah', questionCount: 6, status: 'In Progress', progress: 40, deadline: '10 SEP', deadlineTone: 'upcoming' },
    { id: 10, title: 'Cell Structure Review', subject: 'Science', due: '15 Sep · 8:00 AM', teacher: 'Cikgu Sofia', questionCount: 8, status: 'Not Started', progress: 0, deadline: '15 SEP', deadlineTone: 'upcoming' },
];
