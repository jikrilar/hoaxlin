import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';

const cssDir = new URL('../../resources/css/', import.meta.url);
const read = name => readFileSync(new URL(name, cssDir), 'utf8');

const tokens = read('design-tokens.css');
const tokenValue = name => {
    const match = new RegExp(`--${name}\\s*:\\s*([^;]+);`).exec(tokens);
    assert.ok(match, `token --${name} must exist in design-tokens.css`);
    return match[1].trim();
};

const pageFiles = readdirSync(cssDir).filter(file => file.endsWith('.css') && file !== 'design-tokens.css');

test('typography scale defines display, h1-h4, lead, body, small and meta with size, leading and weight', () => {
    for (const role of ['display', 'editorial-heading', 'page-title', 'section-title', 'item-title', 'lead', 'body', 'body-sm', 'meta']) {
        for (const property of ['size', 'leading', 'weight']) {
            tokenValue(`ds-type-${role}-${property}`);
        }
    }
    // h4 shares the item-title step; small maps to body-sm and meta to meta. Both are explicit roles.
    assert.match(tokens, /--ds-type-item-title-size/, 'h4 must resolve to a defined step');
    assert.match(tokens, /--ds-type-body-sm-size/, 'small must resolve to a defined step');
});

test('base body size is at least 16px and reading measure stays between 60 and 75 characters', () => {
    assert.equal(tokenValue('ds-type-body-size'), '1rem');
    const measure = tokenValue('ds-reading-measure');
    const characters = Number(/^(\d+(?:\.\d+)?)ch$/.exec(measure)?.[1]);
    assert.ok(Number.isFinite(characters), '--ds-reading-measure must be expressed in ch');
    assert.ok(characters >= 60 && characters <= 75, `reading measure ${measure} must be 60-75ch`);
});

test('tracking tokens exist for editorial and UI roles', () => {
    tokenValue('ds-tracking-editorial');
    tokenValue('ds-tracking-ui');
    const ui = Number(tokenValue('ds-tracking-ui').replace('em', ''));
    assert.ok(Number.isFinite(ui) && Math.abs(ui) <= 0.02, 'UI tracking must stay subtle');
});

test('Newsreader stays the editorial heading face and Inter stays the UI face', () => {
    assert.match(tokenValue('ds-font-editorial'), /'Newsreader'/);
    assert.match(tokenValue('ds-font-ui'), /'Inter'/);
    const base = read('base.css');
    assert.match(base, /h1,\s*h2,\s*h3,\s*h4[^{]*\{[^}]*font-family:\s*var\(--ds-font-editorial\)/, 'h1-h4 must use the editorial face by default');
    assert.match(base, /body\s*\{[^}]*font-family:\s*var\(--ds-font-ui\)/, 'body must use the UI face');
});

test('UI surfaces (auth forms) opt headings back into the Inter face', () => {
    const css = read('components.css');
    const authRule = css.match(/\.hl-auth-title h1, \.hl-auth-heading \{[^}]*\}/);
    assert.ok(authRule, 'auth heading rule must exist');
    assert.match(authRule[0], /font-family:\s*var\(--ds-font-ui\)/, 'auth heading must stay in the UI face');
    assert.match(authRule[0], /font-size:\s*var\(--ds-type-h4-size\)/, 'auth heading must use the type scale');
});

test('page stylesheets contain no hard-coded font sizes or line heights', () => {
    for (const file of pageFiles) {
        const css = read(file);
        // Inline glyph markers may scale with em; everything else must use the token scale.
        const withoutInlineGlyphs = css.replace(/\.hl-term__mark\s*\{[^}]*\}/g, '');
        assert.doesNotMatch(withoutInlineGlyphs, /font-size:\s*(?!var\()[0-9]/, `${file} must use --ds-type-* tokens for font sizes`);
        assert.doesNotMatch(withoutInlineGlyphs, /line-height:\s*(?!var\()[0-9]/, `${file} must use --ds-type-* tokens for line heights`);
    }
});

test('paragraph reading widths use the shared reading measure token', () => {
    for (const file of pageFiles) {
        const css = read(file);
        // Truncation widths (e.g. an account name) are not reading measures.
        const readingWidths = [...css.matchAll(/^\s*([^{}\n]*max-inline-size:\s*[0-9]+ch)/gm)].map(m => m[1]);
        assert.deepEqual(readingWidths.filter(rule => /\bp\b|\.hl-.*__(note|hint|description|text)/.test(rule)), [], `${file} must use var(--ds-reading-measure) for prose`);
    }
});

test('section rhythm uses spacing tokens rather than ad-hoc values', () => {
    const information = read('information.css');
    const statistics = read('statistics.css');
    atSectionRhythm(information, 'information.css');
    atSectionRhythm(statistics, 'statistics.css');
});

function atSectionRhythm(css, file) {
    assert.doesNotMatch(css, /(margin-block-start|padding-block-start):\s*(?!var\(|calc\(|0\b|auto)[0-9.]/, `${file} must use --ds-space-* for vertical rhythm`);
}
