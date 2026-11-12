import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/components.css', import.meta.url), 'utf8');
const tokens = readFileSync(new URL('../../resources/css/design-tokens.css', import.meta.url), 'utf8');
const rule = selector => css.match(new RegExp(`${selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\{([^}]*)\\}`))?.[1] ?? '';

// Multi-line blocks (information.css) keep their body inside @layer.
const blockOf = (source, selector) => {
    const start = source.indexOf(`${selector} {`);
    if (start === -1) return '';
    return source.slice(start, source.indexOf('}', start));
};

test('container is fluid and centres content at the shell width', () => {
    const container = rule('.container-app');
    assert.match(container, /width:\s*100%/);
    assert.match(container, /max-width:\s*var\(--ds-container-shell\)/);
    assert.match(container, /margin-inline:\s*auto/);
});

test('each page kind declares one content width token', () => {
    const widths = {
        '.hl-submission-page': '--ds-container-form',
        '.hl-reading-page': '--ds-container-reading',
        '.hl-result-page__inner': '--ds-container-result',
        '.hl-auth-content': '--ds-container-auth',
        '.hl-history-page': '--ds-container-history',
    };
    for (const [selector, token] of Object.entries(widths)) {
        assert.ok(tokens.includes(`${token}:`), `${token} must exist`);
        const files = { '.hl-reading-page': 'information.css', '.hl-result-page__inner': 'result.css', '.hl-history-page': 'history.css' };
        const body = files[selector]
            ? blockOf(readFileSync(new URL(`../../resources/css/${files[selector]}`, import.meta.url), 'utf8'), selector)
            : rule(selector);
        assert.match(body, new RegExp(`${token}\\)`), `${selector} must use ${token}`);
    }
    // Statistics matches the reading width so its content aligns with other information pages;
    // wide tables scroll inside their own region instead of widening the page.
    assert.match(
        blockOf(readFileSync(new URL('../../resources/css/statistics.css', import.meta.url), 'utf8'), '.hl-statistics-page'),
        /max-inline-size:\s*var\(--ds-container-reading\)/,
    );
});

test('nothing in page CSS hard-codes a container width', () => {
    const files = ['information.css', 'history.css', 'statistics.css', 'profile.css', 'result.css', 'submission-progress.css'];
    for (const file of files) {
        const source = readFileSync(new URL(`../../resources/css/${file}`, import.meta.url), 'utf8');
        // Reading measure (ch) and breakpoints (media queries) are not container widths.
        const withoutQueries = source.replace(/@media[^{]+\{/g, '');
        const widths = [...withoutQueries.matchAll(/max-(?:inline-size|width):\s*\d+rem/g)];
        assert.deepEqual(widths.map(m => m[0]), [], `${file} must use a --ds-container-* token`);
    }
});

test('header is sticky only after JS marks it ready and keeps a 44px row', () => {
    assert.match(rule('.hl-header'), /background:\s*var\(--ds-bg-canvas\)/);
    assert.match(css, /\.hl-header\[data-navigation-ready\]\s*\{[^}]*position:\s*fixed/);
    assert.match(css, /\.hl-header\[data-navigation-ready\]\s*\{[^}]*z-index:\s*var\(--ds-z-header\)/);
    assert.match(rule('.hl-nav-link, .hl-nav-toggle'), /min-block-size:\s*2\.75rem/);
});

test('mobile menu toggle is not the sticky bar itself and collapses on wide screens', () => {
    const mediaBlock = css.match(/@media \(max-width: 56\.25rem\)\s*\{[\s\S]*?\n    \}/)?.[0] ?? '';
    assert.ok(mediaBlock.includes('.hl-nav-panel'), 'narrow layout must control the nav panel');
    assert.match(css, /\.hl-nav-toggle\s*\{[^}]*border-color:\s*var\(--ds-border-control\)/);
});
