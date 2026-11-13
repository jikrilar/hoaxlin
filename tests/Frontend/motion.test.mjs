import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const cssDir = new URL('../../resources/css/', import.meta.url);
const read = name => readFileSync(new URL(name, cssDir), 'utf8');
const tokens = read('design-tokens.css');
const base = read('base.css');
const components = read('components.css');
const progress = read('submission-progress.css');
const result = read('result.css');

const reducedMotionBlock = tokens.slice(tokens.indexOf('@media (prefers-reduced-motion: reduce)'));

test('motion tokens define the fast/base/slow scale, easing, and travel distance', () => {
    for (const [name, value] of [
        ['--ds-motion-fast', '120ms'],
        ['--ds-motion-base', '200ms'],
        ['--ds-motion-slow', '320ms'],
    ]) {
        assert.match(tokens, new RegExp(`${name}: ${value};`), `${name} must be ${value}`);
    }
    assert.match(tokens, /--ds-ease-standard: cubic-bezier\(/);
    assert.match(tokens, /--ds-ease-emphasis: cubic-bezier\(/);
    assert.ok(Number(tokens.match(/--ds-motion-rise: (\d+)px/)?.[1]) <= 8, 'movement must stay within 8px');
});

test('reduced motion collapses every motion token to zero', () => {
    assert.ok(reducedMotionBlock.length > 0, 'reduced-motion block must exist');
    for (const name of ['--ds-motion-fast', '--ds-motion-base', '--ds-motion-slow']) {
        assert.match(reducedMotionBlock, new RegExp(`${name}: 0ms;`), `${name} must collapse under reduced motion`);
    }
    assert.match(reducedMotionBlock, /--ds-motion-rise: 0px;/, 'travel distance must collapse under reduced motion');
    for (const selector of ['.hl-disclosure__content', '.hl-flash', '.hl-dialog[open]', '.hl-result-page__content', '[data-input-panel]', '[data-video-panel]']) {
        assert.ok(reducedMotionBlock.includes(selector), `${selector} entrance must be neutralised under reduced motion`);
    }
    assert.match(reducedMotionBlock, /animation: none !important/);
});

test('reduced motion also stops animations and scroll smoothing globally', () => {
    const block = reducedMotionBlock.slice(reducedMotionBlock.indexOf('*'));
    assert.match(block, /animation-duration: 0\.01ms !important/);
    assert.match(block, /animation-iteration-count: 1 !important/);
    assert.match(block, /transition-duration: 0s !important/);
    assert.match(block, /scroll-behavior: auto !important/);
});

test('transitions use motion tokens instead of literal durations', () => {
    for (const [file, css] of [['components.css', components], ['submission-progress.css', progress], ['result.css', result], ['base.css', base]]) {
        const transitions = [...css.matchAll(/transition:[^;]+;/g)].map(m => m[0]);
        for (const rule of transitions) {
            assert.doesNotMatch(rule, /\b\d+(\.\d+)?m?s\b/, `${file} must use --ds-motion-* durations: ${rule}`);
        }
    }
});

test('every transition and animation declares an explicit property', () => {
    for (const [file, css] of [['components.css', components], ['submission-progress.css', progress], ['result.css', result]]) {
        assert.doesNotMatch(css, /transition:\s*all\b/, `${file} must not transition every property`);
        for (const rule of css.matchAll(/transition:\s*([^;]+);/g)) {
            const first = rule[1].trim().split(/\s+/)[0];
            assert.ok(/^[a-z-]+$/.test(first), `${file} transition must start with a property: ${rule[0]}`);
        }
    }
});

test('only the processing indicator loops forever', () => {
    const loopers = [...components.matchAll(/animation:\s*([^;]+);/g)]
        .map(m => m[1])
        .filter(value => /\binfinite\b/.test(value));
    assert.deepEqual(loopers.map(rule => rule.split(/\s+/)[0]), ['hl-spin'], 'the spinner is the sole continuous animation');
    assert.match(components, /\.hl-button__spinner[^}]*animation: hl-spin/);
    for (const [file, css] of [['submission-progress.css', progress], ['result.css', result], ['base.css', base]]) {
        assert.doesNotMatch(css, /animation:[^;]*infinite/, `${file} must not add endless animation`);
    }
});

test('disclosure, flash, dialog, progress and result reveal are wired to the motion scale', () => {
    assert.match(components, /\.hl-disclosure__content[^}]*animation: hl-disclosure-reveal/, 'disclosure content animates on open');
    assert.match(components, /@keyframes hl-disclosure-reveal/, 'disclosure keyframes exist');
    assert.match(components, /\.hl-flash[^{]*\{[^}]*animation: hl-flash-enter/, 'flash messages animate in');
    assert.match(components, /\.hl-dialog\[open\][^}]*animation: hl-dialog-enter/, 'dialog animates on open');
    assert.match(progress, /\.hl-submission-progress__fill[^}]*transition: width/, 'progress fill animates width');
    assert.match(result, /\.hl-result-page__content[^}]*animation: hl-result-reveal/, 'result content reveals on arrival');
    assert.match(result, /@keyframes hl-result-reveal/, 'result reveal keyframes exist');
    assert.match(components, /\[data-submission-selector\]\[data-enhanced\] \[data-input-panel\]:not\(\[hidden\]\)[^{]*\{[^}]*animation: hl-panel-enter/, 'input tabs ease their panel in');
    assert.match(components, /@keyframes hl-panel-enter/, 'panel entrance keyframes exist');
});

test('reveal animations move no more than --ds-motion-rise', () => {
    const cap = Number(tokens.match(/--ds-motion-rise: (\d+)px/)?.[1]);
    assert.ok(cap > 0 && cap <= 8, 'travel cap must be at most 8px');
    for (const [file, css] of [['components.css', components], ['result.css', result]]) {
        for (const keyframes of css.matchAll(/@keyframes [^{]+\{[\s\S]*?\n    \}/g)) {
            const name = keyframes[0].match(/@keyframes ([\w-]+)/)[1];
            const offsets = [...keyframes[0].matchAll(/translateY\((\d+(?:\.\d+)?)px\)/g)].map(m => Number(m[1]));
            assert.deepEqual(offsets.filter(px => px !== 0), [], `${file} ${name} must use --ds-motion-rise, not literal pixels`);
            const usesToken = /translateY\(var\(--ds-motion-rise\)\)/.test(keyframes[0]);
            const moves = /translateY\(/.test(keyframes[0]);
            if (moves) assert.ok(usesToken, `${file} ${name} must slide by --ds-motion-rise`);
        }
    }
});
