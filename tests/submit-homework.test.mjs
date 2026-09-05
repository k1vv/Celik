import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false, ws: false }, appType: 'custom' });
after(() => server.close());
const { SubmitHomeworkPage } = await server.ssrLoadModule('/resources/js/Pages/SubmitHomework.jsx');

test('submit homework presents the upload workflow and released work', () => {
    const html = renderToStaticMarkup(createElement(SubmitHomeworkPage));

    assert.match(html, /Drop your homework here/);
    assert.match(html, /Attach your homework first/);
    assert.match(html, /disabled=""/);
    assert.match(html, /Released to you/);
    assert.match(html, /Needs practice/);
    assert.match(html, /YOUR NEXT PRACTICE/);
});
