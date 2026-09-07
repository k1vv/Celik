import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { DueSoonPage, MarkedWorkPage } = await server.ssrLoadModule('/resources/js/Components/Student/StudentWorkPages.jsx');

test('marked work shows answer analysis, issue cards, and practice', () => {
    const html = renderToStaticMarkup(createElement(MarkedWorkPage));

    assert.match(html, /Your marked answer/);
    assert.match(html, /WHAT WENT WRONG/);
    assert.match(html, /Metal is naturally colder than wood/);
    assert.match(html, /Go to practice/);
});

test('due work shows the provided empty state when no assignments exist', () => {
    const html = renderToStaticMarkup(createElement(DueSoonPage, { featured: null, assignments: [] }));

    assert.match(html, /You&#x27;re all caught up|You're all caught up/);
    assert.match(html, /No homework is due right now/);
});

test('due work leaves out assignments that were already handed in', () => {
    const assignment = { question_id: 1, due_at: null, skill_name: 'Heat' };
    const html = renderToStaticMarkup(createElement(DueSoonPage, {
        assignments: [
            { ...assignment, id: 1, title: 'Handed in', submitted: true },
            { ...assignment, id: 2, title: 'Still due', submitted: false },
        ],
    }));

    assert.match(html, /Still due/);
    assert.doesNotMatch(html, /Handed in/);
});

test('marked work names the teacher as the source of a teacher mark', () => {
    const html = renderToStaticMarkup(createElement(MarkedWorkPage, {
        attempts: [{
            id: 1, question_body: 'Why?', raw_answer: 'Because.', is_correct: false, score: 15, max_score: 20,
            needs_review: false, teacher_marked: true, created_at: '2026-01-02T08:00:00.000000Z', diagnoses: [],
        }],
    }));

    assert.match(html, /Marked by teacher/);
    assert.match(html, /15\/20/);
});
