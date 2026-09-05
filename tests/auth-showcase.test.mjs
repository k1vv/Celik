import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());

const { showcaseSlides } = await server.ssrLoadModule('/resources/js/Components/Auth/showcaseSlides.js');
const { default: LearningPreview } = await server.ssrLoadModule('/resources/js/Components/Auth/LearningPreview.jsx');

test('showcase provides five distinct Celik learning scenarios', () => {
    assert.equal(showcaseSlides.length, 5);
    assert.deepEqual(showcaseSlides.map((slide) => slide.type), ['practice', 'feedback', 'personalised', 'progress', 'teacher']);
});

test('every showcase scenario renders its own education preview', () => {
    const expectedContent = {
        practice: 'Adding unlike fractions',
        feedback: 'Teacher feedback',
        personalised: 'Fraction equivalence',
        progress: '12% improvement this week',
        teacher: 'Equivalent fractions',
    };

    for (const [type, content] of Object.entries(expectedContent)) {
        const html = renderToStaticMarkup(createElement(LearningPreview, { type }));

        assert.match(html, new RegExp(content, 'i'));
    }
});
