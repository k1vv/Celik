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
