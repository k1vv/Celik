import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { App } = await server.ssrLoadModule('@inertiajs/react');
const { HomeworkForm, SubmitHomeworkPage } = await server.ssrLoadModule('/resources/js/Pages/SubmitHomework.jsx');
const { availableModes, homeworkRequest, isGrading } = await server.ssrLoadModule('/resources/js/Components/Student/homeworkRequest.js');

const single = { id: 7, title: 'Homework 1', question_id: 3, submitted: false, questions: [{ id: 3, order: 1, body: 'Why is metal cold?' }] };
const multiple = { ...single, id: 8, title: 'Homework 2', questions: [...single.questions, { id: 4, order: 2, body: 'Define heat.' }] };

const processed = {
    id: 21, assignment_title: 'Homework 2', extraction_status: 'extracted', question_count: 2, answered_count: 1,
    needs_review_count: 1, teacher_validated_at: null, created_at: '2026-01-02T08:00:00.000000Z',
};

function render(component, props, page = {}) {
    const initialPage = { component: 'Test', url: '/', version: null, props: { errors: {}, flash: {}, ...page } };

    return renderToStaticMarkup(createElement(App, {
        initialPage,
        initialComponent: () => createElement(component, props),
        resolveComponent: (name) => name,
    }));
}

test('a PDF is submitted for the whole assignment', () => {
    const pdf = { name: 'work.pdf' };

    assert.deepEqual(homeworkRequest('pdf', multiple, { pdf, photo: null, answer: '' }), {
        url: '/submissions', data: { assignment_id: 8, pdf },
    });
});

test('a photo is submitted for the single question without the other fields', () => {
    const photo = { name: 'work.jpg' };

    assert.deepEqual(homeworkRequest('photo', single, { pdf: { name: 'old.pdf' }, photo, answer: 'unused' }), {
        url: '/homework', data: { question_id: 3, photo },
    });
});

test('a typed answer is submitted for the single question without the other fields', () => {
    assert.deepEqual(homeworkRequest('typed', single, { pdf: null, photo: { name: 'old.jpg' }, answer: 'Conduction.' }), {
        url: '/homework', data: { question_id: 3, answer: 'Conduction.' },
    });
});

test('nothing can be submitted until the selected mode has an answer', () => {
    const empty = { pdf: null, photo: null, answer: '   ' };

    for (const mode of ['pdf', 'photo', 'typed']) {
        assert.equal(homeworkRequest(mode, single, empty), null);
    }
});

test('an assignment with several questions only accepts a PDF', () => {
    assert.deepEqual(availableModes(single), ['pdf', 'photo', 'typed']);
    assert.deepEqual(availableModes(multiple), ['pdf']);
});

test('the form shows every question and starts on PDF upload with submit disabled', () => {
    const html = render(HomeworkForm, { assignment: multiple });

    assert.match(html, /Why is metal cold\?/);
    assert.match(html, /Define heat\./);
    assert.match(html, /accept="application\/pdf,\.pdf"/);
    assert.match(html, /<button type="submit" disabled=""/);
    assert.doesNotMatch(html, /Type answer/);
});

test('a single-question assignment also offers photo and typed answers', () => {
    const html = render(HomeworkForm, { assignment: single });

    assert.match(html, /Photo/);
    assert.match(html, /Type answer/);
});

test('a submitted assignment is labelled in the assignment list', () => {
    const html = render(SubmitHomeworkPage, { assignments: [{ ...single, submitted: true }, multiple] });

    assert.match(html, /Homework 1 · Already submitted/);
    assert.doesNotMatch(html, /Homework 2 · Already submitted/);
});

test('the result of the PDF just submitted says what was read and what awaits the teacher', () => {
    const html = render(SubmitHomeworkPage, { submissions: [processed] }, { flash: { submission_id: 21 } });

    assert.match(html, /Homework 2 was graded/);
    assert.match(html, /1 of 2 answers read/);
    assert.match(html, /1 waiting for your teacher/);
});

test('no result panel is shown when nothing was just submitted', () => {
    const html = render(SubmitHomeworkPage, { submissions: [processed] });

    assert.doesNotMatch(html, /Homework 2 was graded/);
});

test('PDF submissions are listed with their status', () => {
    const html = render(SubmitHomeworkPage, {
        submissions: [
            processed,
            { ...processed, id: 22, assignment_title: 'Homework 3', extraction_status: 'failed' },
            { ...processed, id: 23, assignment_title: 'Homework 4', teacher_validated_at: '2026-01-03T08:00:00.000000Z' },
            { ...processed, id: 24, assignment_title: 'Homework 5', needs_review_count: 0 },
        ],
    });

    assert.match(html, /Homework 2.*?Waiting for teacher/s);
    assert.match(html, /Homework 3.*?Could not be read/s);
    assert.match(html, /Homework 4.*?Checked by teacher/s);
    assert.match(html, /Homework 5.*?Graded/s);
});

test('a PDF still being graded says so instead of reporting results', () => {
    const pending = { ...processed, extraction_status: 'pending', answered_count: 0, needs_review_count: 0 };
    const html = render(SubmitHomeworkPage, { submissions: [pending] }, { flash: { submission_id: 21 } });

    assert.match(html, /Homework 2 was handed in/);
    assert.match(html, /Being graded/);
    assert.doesNotMatch(html, /answers read/);
});

test('a PDF that failed asks the student to hand it in again', () => {
    const failed = { ...processed, extraction_status: 'failed', answered_count: 0, needs_review_count: 0 };
    const html = render(SubmitHomeworkPage, { submissions: [failed] }, { flash: { submission_id: 21 } });

    assert.match(html, /could not be read/i);
    assert.match(html, /hand it in again/);
    assert.doesNotMatch(html, /answers read/);
});

test('the page only keeps refreshing while a PDF is being graded', () => {
    assert.equal(isGrading([processed]), false);
    assert.equal(isGrading([processed, { ...processed, extraction_status: 'pending' }]), true);
    assert.equal(isGrading([]), false);
});

test('an answer marked by the teacher is shown as theirs, not as needing practice', () => {
    const attempt = {
        id: 1, question_id: 3, question_body: 'Why is metal cold?', kssm_code: '4.1', is_correct: false, score: 15, max_score: 20,
        needs_review: false, teacher_marked: true, created_at: '2026-01-02T08:00:00.000000Z', diagnoses: [],
    };
    const card = render(SubmitHomeworkPage, { pastAttempts: [attempt] }).match(/<article.*?<\/article>/s)[0];

    assert.match(card, /15\/20/);
    assert.match(card, /Marked by teacher/);
    assert.doesNotMatch(card, /Needs practice|Pending review/);
});
