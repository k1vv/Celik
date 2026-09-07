import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { default: SubmissionList } = await server.ssrLoadModule('/resources/js/Components/Teacher/SubmissionList.jsx');
const render = (submissions) => renderToStaticMarkup(createElement(SubmissionList, { submissions }));

const submission = {
    id: 9, student_name: 'Ain Nadia', assignment_title: 'Homework 1', extraction_status: 'extracted', question_count: 3,
    answered_count: 2, needs_review_count: 1, teacher_validated_at: null, created_at: '2026-01-02T08:00:00.000000Z',
};

test('a graded submission links to its review page and says what is waiting', () => {
    const html = render([submission]);

    assert.match(html, /href="\/teacher\/submissions\/9"/);
    assert.match(html, /Ain Nadia/);
    assert.match(html, /Homework 1/);
    assert.match(html, /2 of 3 answers read/);
    assert.match(html, /1 to review/);
});

test('a submission that is being graded or failed cannot be opened', () => {
    for (const [status, label] of [['pending', /Being graded/], ['failed', /Could not be read/]]) {
        const html = render([{ ...submission, extraction_status: status }]);

        assert.match(html, label);
        assert.doesNotMatch(html, /href=/);
    }
});

test('a checked submission is labelled and can still be opened', () => {
    const html = render([{ ...submission, teacher_validated_at: '2026-01-03T08:00:00.000000Z' }]);

    assert.match(html, /Checked/);
    assert.doesNotMatch(html, /to review/);
    assert.match(html, /href="\/teacher\/submissions\/9"/);
});

test('a submission the AI was sure about is labelled as not checked yet', () => {
    assert.match(render([{ ...submission, needs_review_count: 0 }]), /Not checked yet/);
});

test('an empty list explains when submissions appear', () => {
    assert.match(render([]), /No PDF submissions yet/);
});
