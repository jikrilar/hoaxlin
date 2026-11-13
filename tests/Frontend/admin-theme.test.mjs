import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const themeUrl = new URL('../../resources/css/filament/admin/theme.css', import.meta.url);
const theme = readFileSync(themeUrl, 'utf8');
const tokens = readFileSync(new URL('../../resources/css/design-tokens.css', import.meta.url), 'utf8');
const provider = readFileSync(new URL('../../app/Providers/Filament/AdminPanelProvider.php', import.meta.url), 'utf8');
const block = selector => theme.match(new RegExp(`${selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\{([^}]*)\\}`))?.[1] ?? '';

test('admin theme imports the shared tokens and fonts, not the public shell', () => {
    assert.match(theme, /@import '\.\.\/\.\.\/design-tokens\.css'/);
    assert.match(theme, /@import '\.\.\/\.\.\/fonts\/inter\.css'/);
    // Preflight/components belong to the public app; the admin keeps Filament's own.
    for (const forbidden of ['app.css', 'base.css', 'components.css', 'theme.css\'']) {
        assert.ok(!theme.includes(`@import '../../${forbidden}`), `admin must not import ${forbidden}`);
    }
});

test('admin headings use the shared type scale and tracking', () => {
    const heading = block('.fi-panel-admin :is(.fi-header-heading, .fi-simple-header-heading)');
    assert.match(heading, /font-size:\s*var\(--ds-type-h2-size\)/, 'page headings use the h2 step');
    assert.match(heading, /letter-spacing:\s*var\(--ds-type-h2-tracking\)/);
    assert.match(heading, /line-height:\s*var\(--ds-type-h2-leading\)/);
    assert.match(heading, /font-family:\s*var\(--ds-font-ui\)/, 'admin stays in the UI face');
});

test('admin tables, badges and notifications use tokens rather than raw values', () => {
    const table = block('.fi-panel-admin .fi-ta');
    assert.ok(table.length > 0, 'table rules must exist');
    assert.match(table, /font-size:\s*var\(--ds-type-body-sm-size\)/);
    for (const rule of theme.matchAll(/\.fi-panel-admin[^{]*\{([^}]*)\}/g)) {
        assert.doesNotMatch(rule[1], /font-size:\s*\d+(\.\d+)?(px|rem)/, `raw font size in: ${rule[0].slice(0, 60)}`);
    }
});

test('admin motion uses the stage-2 scale and honours reduced motion', () => {
    // Shorthand or longhand both count, as long as the stage-2 scale is used.
    // The reduced-motion safety net legitimately hard-codes 0s; check the rest.
    const motionFree = theme.slice(0, theme.indexOf('@media (prefers-reduced-motion: reduce)'));
    const transitions = [...motionFree.matchAll(/transition(?:-[a-z]+)?:[^;]+;/g)].map(m => m[0]);
    assert.ok(transitions.length > 0, 'admin declares transitions');
    assert.ok(transitions.some(rule => rule.includes('var(--ds-motion-fast)')), 'transitions use --ds-motion-fast');
    assert.ok(transitions.some(rule => rule.includes('var(--ds-ease-standard)')), 'transitions use --ds-ease-standard');
    for (const rule of transitions) {
        assert.doesNotMatch(rule, /\b\d+(?:\.\d+)?m?s\b/, `raw duration in: ${rule}`);
    }
    const reduced = theme.slice(theme.indexOf('@media (prefers-reduced-motion: reduce)'));
    assert.ok(reduced.length > 0, 'reduced-motion block must exist');
    for (const declaration of ['animation-duration: 0.01ms !important', 'animation-iteration-count: 1 !important', 'transition-duration: 0s !important', 'scroll-behavior: auto !important']) {
        assert.ok(reduced.includes(declaration), `reduced motion must declare ${declaration}`);
    }
});

test('Filament colour map stays aligned with design tokens', () => {
    const palette = Object.fromEntries([...tokens.matchAll(/--p-([a-z0-9-]+):\s*(#[0-9a-f]{6})/gi)].map(m => [m[1].toLowerCase(), m[2].toLowerCase()]));
    const providerColors = provider.slice(provider.indexOf("->colors(["));
    const pairs = [...providerColors.matchAll(/(\d+)\s*=>\s*'(#[0-9a-fA-F]{6})'/g)].map(m => [m[1], m[2].toLowerCase()]);

    assert.ok(pairs.length >= 20, 'both primary and gray scales are declared');
    for (const [, hex] of pairs) {
        assert.ok(Object.values(palette).includes(hex), `${hex} must exist in design-tokens.css`);
    }
    // Primary 600 and gray 500/900 keep the documented token values.
    assert.match(providerColors, /600 => '#235c50'/);
    assert.match(providerColors, /500 => '#647069'/);
    assert.match(providerColors, /900 => '#1f2b29'/);
});

test('badge tones target the classes Filament actually emits', () => {
    // Filament renders `.fi-badge.fi-color-<name>` and paints from `--color-*`.
    for (const tone of ['success', 'danger', 'warning', 'info']) {
        const rule = block(`.fi-panel-admin .fi-badge.fi-color-${tone}`);
        assert.ok(rule.length > 0, `badge tone ${tone} must be styled`);
        assert.match(rule, new RegExp(`background:\\s*var\\(--ds-${tone}-bg\\)`));
        assert.match(rule, new RegExp(`color:\\s*var\\(--ds-${tone}-fg\\)`));
    }
});
