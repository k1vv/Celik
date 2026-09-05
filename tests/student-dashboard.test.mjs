import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { HomeworkChart, LearningGapItem, ProgressBar, EmptyState } = await server.ssrLoadModule('/resources/js/Components/Student/DashboardComponents.jsx');
const { SubmitHomeworkPage } = await server.ssrLoadModule('/resources/js/Pages/SubmitHomework.jsx');
const render = (component, props) => renderToStaticMarkup(createElement(component, props));

test('empty homework history explains where progress will appear', () => {
    const html = render(HomeworkChart, { items: [] });
    assert.match(html, /Your progress starts here/);
    assert.match(html, /Cleared learning gaps will appear/);
    assert.doesNotMatch(html, /role="img"/);
});

test('chart exposes every value including zero to assistive technology', () => {
    const html = render(HomeworkChart, { items: [{ label: 'HW1', value: 0 }, { label: 'HW2', value: 3 }] });
    assert.match(html, /aria-label="HW1: 0 gaps cleared, HW2: 3 gaps cleared"/);
    assert.doesNotMatch(html, /NaN|Infinity/);
});

test('learning gaps show active and completed states with accessible actions', () => {
    const gap = { code: 'S-04', title: 'Heat transfer', standard: '4.2.1', source: 'Homework 6', completed: 1, total: 3, cleared: false };
    const active = render(LearningGapItem, { gap });
    assert.match(active, /Practice open/);
    assert.match(active, /aria-label="Practice S-04"/);
    assert.match(active, /aria-valuenow="33"/);
    const cleared = render(LearningGapItem, { gap: { ...gap, completed: 3, cleared: true } });
    assert.match(cleared, /Cleared/);
    assert.match(cleared, /aria-label="Review S-04"/);
    assert.match(cleared, /aria-valuenow="100"/);
});

test('progress remains valid with empty or out-of-range data', () => {
    for (const [completed, total, expected] of [[0, 0, 0], [-1, 3, 0], [5, 3, 100]]) {
        assert.match(render(ProgressBar, { completed, total, label: 'Questions' }), new RegExp(`aria-valuenow="${expected}"`));
    }
});

test('empty sections show a helpful message', () => {
    const html = render(EmptyState, { title: "You're all caught up.", description: 'No active learning gaps have been identified.' });
    assert.match(html, /No active learning gaps have been identified/);
});

test('submit homework page renders its local-only submission and released-work UI', () => {
    const html = render(SubmitHomeworkPage);

    assert.match(html, /Drop your homework here/);
    assert.match(html, /Homework 7 · Heat and energy transfer/);
    assert.match(html, /Released to you/);
    assert.match(html, /4 marked · 1 new/);
    assert.match(html, /Sample submission interface · uploading is not connected yet/);
    assert.doesNotMatch(html, /<form|href=/);
});
