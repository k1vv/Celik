import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { PracticeSetCard } = await server.ssrLoadModule('/resources/js/Pages/Practice.jsx');

test('teacher-assigned practice cards show their source, focus gaps, and action', () => {
    const html = renderToStaticMarkup(createElement(PracticeSetCard, {
        onOpen: () => {},
        practiceSet: {
            id: 'heat', title: 'Heat Transfer Foundations', subject: 'Science', teacher: 'Cikgu Rahimah', initials: 'CR', description: 'Strengthen heat transfer.', questionCount: 5, estimatedMinutes: 10, difficulty: 'Foundation', progress: 0, status: 'assigned', dueDate: '8 Sep', gaps: [{ code: 'S-04' }, { code: 'A-01' }],
        },
    }));

    assert.match(html, /Recommended by Cikgu Rahimah/);
    assert.match(html, /S-04/);
    assert.match(html, /Start practice/);
});
