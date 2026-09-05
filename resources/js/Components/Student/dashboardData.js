// TODO: Replace sample summaries with teacher-confirmed learning-gap data.
export const mockDashboardStats = { submitted: 24, gapsCleared: 6, totalGaps: 8, practiceWaiting: 3 };
export const mockStudent = { name: 'Student Name', classroom: 'Form 2 Bestari', subject: 'Science', form: 'Form 2', curriculum: 'KSSM' };
export const mockLearningGaps = [
    { code: 'S-04', title: 'Metal feels naturally colder than wood', standard: '4.2.1', source: 'Seen in Homework 6', completed: 1, total: 3, cleared: false },
    { code: 'A-01', title: 'States without explaining', standard: 'Scientific communication', source: 'Seen in Homework 5 and 6', completed: 1, total: 2, cleared: false },
    { code: 'S-08', title: 'Heat rises, rather than hot air rises', standard: '4.3.1', source: 'Seen in Homework 4 · Cleared 22 Aug', completed: 4, total: 4, cleared: true },
];
export const mockHomeworkProgress = [1, 2, 0, 2, 1, 3].map((value, index) => ({ label: `HW${index + 1}`, value }));
export const mockRecentWork = [
    { id: 6, mark: 14, maximum: 20, title: 'Homework 6 · Heat and Temperature', date: '2 Sep', gap: 'S-04' },
    { id: 5, mark: 17, maximum: 20, title: 'Homework 5 · Energy Changes', date: '28 Aug', gap: 'A-01' },
];
// TODO: Replace with personalised practice API/data.
export const mockNextPractice = { code: 'S-04', title: 'Three questions built for the mistake you made', description: 'Targeted to learning standard 4.2.1 — metal conducts heat away from your hand faster than wood, so it feels colder at the same temperature.', completed: 1, questions: 3, minutes: 5 };
// TODO: Replace with upcoming assignments when homework is connected.
export const mockNextDue = { title: 'Homework 7', topic: 'Heat and Energy Transfer', due: 'Friday, 8:00 AM' };
