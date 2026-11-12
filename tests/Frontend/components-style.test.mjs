import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/components.css', import.meta.url), 'utf8');
const uiDir = new URL('../../resources/views/components/ui/', import.meta.url);
const viewsDir = new URL('../../resources/views/', import.meta.url);
const block = name => css.match(new RegExp(`\\${name}\\s*\\{[^}]*\\}`))?.[0] ?? '';

test('every new component has exactly one style block', () => {
    for (const name of ['.hl-card', '.hl-badge', '.hl-tabs__tab', '.hl-stat', '.hl-stepper']) {
        assert.ok(block(name).length > 0, `${name} must be styled`);
        assert.equal(css.split(`${name} {`).length - 1, 1, `${name} must not be redefined`);
    }
});

test('buttons share one style; page CSS does not restyle them', () => {
    const pageFiles = readdirSync(new URL('../../resources/css/', import.meta.url))
        .filter(file => file.endsWith('.css') && file !== 'components.css' && file !== 'theme.css' && file !== 'design-tokens.css');
    for (const file of pageFiles) {
        const page = readFileSync(new URL(`../../resources/css/${file}`, import.meta.url), 'utf8');
        // Layout-only rules (flex growth inside an actions row) are allowed; visual restyling is not.
        for (const rule of page.matchAll(/\.hl-button[^{]*\{([^}]*)\}/g)) {
            for (const declaration of rule[1].split(';').map(value => value.trim()).filter(Boolean)) {
                const property = declaration.split(':')[0].trim();
                assert.ok(/^(flex|margin|inline-size|block-size|min-inline-size)/.test(property), `${file} must not restyle buttons (${declaration})`);
            }
        }
    }
    for (const name of ['sm', 'lg']) {
        assert.ok(css.includes(`.hl-button[data-size="${name}"]`), `button size ${name} must be shared`);
    }
});

test('cards reuse one surface language instead of per-page panels', () => {
    assert.match(block('.hl-card'), /border-radius: var\(--ds-radius-lg\)/);
    assert.match(block('.hl-card'), /background: var\(--ds-bg-surface\)/);
    const welcome = readFileSync(new URL('welcome.blade.php', viewsDir), 'utf8');
    assert.match(welcome, /hl-submission-surface/, 'the submission panel keeps its page-specific hook');
});

test('badges always pair an icon with text', () => {
    const badge = readFileSync(new URL('badge.blade.php', uiDir), 'utf8');
    assert.match(badge, /hl-badge__icon[\s\S]*aria-hidden="true"/);
    assert.match(badge, /hl-badge__text/);
    for (const tone of ['success', 'danger', 'warning', 'info']) {
        assert.match(css, new RegExp(`\\.hl-badge\\[data-tone="${tone}"\\]`));
    }
});

test('stepper states are limited to done/current/todo/error', () => {
    const stepper = readFileSync(new URL('stepper.blade.php', uiDir), 'utf8');
    assert.match(stepper, /\$index < \$currentIndex \? 'done' : 'todo'/);
    for (const state of ['done', 'current', 'error', 'todo']) {
        assert.match(css, new RegExp(`\\.hl-stepper \\[data-status="${state}"\\]`));
    }
});

test('component views never hand-roll inline style attributes', () => {
    for (const file of readdirSync(uiDir).filter(name => name.endsWith('.blade.php'))) {
        const source = readFileSync(new URL(file, uiDir), 'utf8');
        assert.doesNotMatch(source, /\sstyle="/, `${file} must use CSS classes, not inline styles`);
    }
});

test('stepper numbers are plain text, never wrapped in a shape', () => {
    // Owner request: the step number is ordinary text — no badge, circle, border, or background.
    const number = block('.hl-stepper__number');
    assert.ok(number.length > 0, 'stepper number must be styled');
    for (const property of ['border', 'background', 'border-radius', 'inline-size', 'block-size', 'box-shadow']) {
        assert.doesNotMatch(number, new RegExp(`${property}\\s*:`), `stepper number must not set ${property}`);
    }
    assert.match(number, /font-variant-numeric:\s*tabular-nums/, 'numbers align without a box');
    assert.doesNotMatch(css, /\.hl-stepper__index/, 'no badge wrapper may return');

    // Ordered lists still carry no CSS marker of their own.
    for (const [file, selector] of [['information.css', '.hl-reading-steps'], ['result.css', '.hl-result-evidence__list']]) {
        const source = readFileSync(new URL(`../../resources/css/${file}`, import.meta.url), 'utf8');
        const body = source.slice(source.indexOf(`${selector} {`), source.indexOf('}', source.indexOf(`${selector} {`)));
        assert.match(body, /list-style:\s*none/, `${selector} must not draw a marker`);
    }
});
