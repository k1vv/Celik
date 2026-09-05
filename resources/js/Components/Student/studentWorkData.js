// TODO BACKEND: Replace these placeholder arrays with data supplied by the
// controller/service/API for the authenticated student.
export const markedWorkSummary = [
    { label: 'Released', value: '6' },
    { label: 'Average Score', value: '78%' },
    { label: 'Needs Review', value: '2' },
];

// TODO BACKEND: Fetch released homework, scores, feedback, concept tags, and
// release dates for the authenticated student.
export const markedWorks = [
    {
        id: 6,
        title: 'Homework 6',
        topic: 'Heat and Temperature',
        subject: 'Science',
        released: '2 Sep 2026',
        releasedOrder: 3,
        score: 14,
        total: 20,
        percentage: 70,
        status: 'Needs Practice',
        statusTone: 'practice',
        teacher: 'Ms. Aina',
        learningAreas: ['Heat vs Temperature', 'Thermal Equilibrium'],
        feedback: 'Good understanding of the basic concept. Review how heat moves between objects at different temperatures.',
    },
    {
        id: 5,
        title: 'Homework 5',
        topic: 'Energy Changes',
        subject: 'Science',
        released: '28 Aug 2026',
        releasedOrder: 2,
        score: 17,
        total: 20,
        percentage: 85,
        status: 'Good Progress',
        statusTone: 'progress',
        teacher: 'Ms. Aina',
        learningAreas: ['Energy Transfer'],
        feedback: 'Strong answer. Make sure to explain the direction of energy transfer clearly.',
    },
    {
        id: 4,
        title: 'Homework 4',
        topic: 'Forces and Motion',
        subject: 'Science',
        released: '20 Aug 2026',
        releasedOrder: 1,
        score: 19,
        total: 20,
        percentage: 95,
        status: 'Excellent',
        statusTone: 'excellent',
        teacher: 'Ms. Aina',
        learningAreas: ['Balanced Forces'],
        feedback: 'Excellent explanations and clear working throughout. Keep applying the same care to every question.',
    },
];

// TODO BACKEND: Fetch upcoming assignments and submission status, then
// calculate deadline and time remaining server-side.
export const dueSoonSummary = [
    { label: 'Due Today', value: '1', tone: 'urgent' },
    { label: 'Due This Week', value: '3', tone: 'soon' },
    { label: 'Upcoming', value: '5', tone: 'upcoming' },
];

export const nextAssignment = {
    id: 7,
    title: 'Homework 7',
    topic: 'Heat and Energy Transfer',
    subject: 'Science',
    due: 'Friday, 8:00 AM',
    timeRemaining: '2 days 8 hours',
    teacher: 'Ms. Aina',
    description: 'Complete all questions about heat transfer, conduction, convection and radiation.',
    status: 'Not Submitted',
};

export const upcomingAssignments = [
    { id: 8, title: 'Homework 8', topic: 'States of Matter', subject: 'Science', due: '10 Sep 2026', timeRemaining: '3 days left', status: 'Not Started', deadline: 'This Week', deadlineTone: 'week' },
    { id: 9, title: 'Homework 9', topic: 'Linear Equations', subject: 'Mathematics', due: '12 Sep 2026', timeRemaining: '5 days left', status: 'In Progress', deadline: 'This Week', deadlineTone: 'week' },
    { id: 10, title: 'Homework 10', topic: 'Cell Structure', subject: 'Science', due: '15 Sep 2026', timeRemaining: '8 days left', status: 'Not Started', deadline: 'Upcoming', deadlineTone: 'upcoming' },
];
