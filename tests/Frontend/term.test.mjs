import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { mount } from '../../resources/js/components/term.js';
function createTerm(id, doc, description = 'Penjelasan istilah.') {
    const attrs = new Map([
        ['aria-expanded', 'false'],
        ['aria-describedby', `popover-${id}`],
        ['title', description],
    ]);
    const trigger = Object.assign(new EventTarget(), {
        id: `trigger-${id}`,
        hidden: false,
        getAttribute: name => attrs.get(name) ?? null,
        setAttribute: (name, value) => attrs.set(name, String(value)),
        removeAttribute: name => attrs.delete(name),
        hasAttribute: name => attrs.has(name),
        focus() { doc.activeElement = this; },
    });
    const popover = { id: `popover-${id}`, hidden: true, textContent: description };
    const container = Object.assign(new EventTarget(), {
        dataset: { term: id },
        querySelector: selector => {
            if (selector === '[data-term-trigger]') return trigger;
            if (selector === '[data-term-popover]') return popover;
            return null;
        },
        contains: node => node === container || node === trigger || node === popover,
    });
    trigger.closest = selector => (selector === '[data-term]' ? container : null);
    popover.closest = selector => (selector === '[data-term]' ? container : null);
    return { container, trigger, popover };
}
function fixture() {
    const doc = Object.assign(new EventTarget(), { activeElement: null });
    const first = createTerm('confidence', doc, 'Seberapa yakin model terhadap tebakannya.');
    const second = createTerm('rag', doc, 'Pencarian dokumen terkait di basis pengetahuan Hoaxlin.');
    const outside = { closest: () => null };
    const root = {
        ownerDocument: doc,
        querySelectorAll: selector => (selector === '[data-term]' ? [first.container, second.container] : []),
        contains: node => node === first.container || node === first.trigger || node === first.popover
            || node === second.container || node === second.trigger || node === second.popover
            || node === outside,
    };
    return { root, doc, first, second, outside };
}

test('click toggles popover, updates aria-expanded, and closes other open term popovers', () => {
    const f = fixture();
    const cleanup = mount(f.root);

    assert.equal(f.first.trigger.hasAttribute('title'), false);
    assert.equal(f.first.popover.hidden, true);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'false');

    f.first.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.first.popover.hidden, false);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'true');

    f.second.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.first.popover.hidden, true);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(f.second.popover.hidden, false);
    assert.equal(f.second.trigger.getAttribute('aria-expanded'), 'true');

    f.second.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.second.popover.hidden, true);
    assert.equal(f.second.trigger.getAttribute('aria-expanded'), 'false');

    cleanup();
});

test('Escape closes open popover and returns focus to the trigger', () => {
    const f = fixture();
    const cleanup = mount(f.root);

    f.first.trigger.dispatchEvent(new Event('click'));
    f.doc.activeElement = f.outside;

    const esc = new Event('keydown', { cancelable: true });
    Object.defineProperty(esc, 'key', { value: 'Escape' });
    f.doc.dispatchEvent(esc);

    assert.equal(esc.defaultPrevented, true);
    assert.equal(f.first.popover.hidden, true);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(f.doc.activeElement, f.first.trigger);

    cleanup();
});

test('outside click and focusout leaving the term close the popover without stealing focus', () => {
    const f = fixture();
    const cleanup = mount(f.root);

    f.first.trigger.dispatchEvent(new Event('click'));
    f.doc.activeElement = null;

    const insideClick = new Event('click');
    Object.defineProperty(insideClick, 'target', { value: f.first.popover });
    f.doc.dispatchEvent(insideClick);
    assert.equal(f.first.popover.hidden, false);

    const outsideClick = new Event('click');
    Object.defineProperty(outsideClick, 'target', { value: f.outside });
    f.doc.dispatchEvent(outsideClick);
    assert.equal(f.first.popover.hidden, true);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(f.doc.activeElement, null);

    f.second.trigger.dispatchEvent(new Event('click'));
    const blurInside = new Event('focusout');
    Object.defineProperty(blurInside, 'relatedTarget', { value: f.second.popover });
    f.second.container.dispatchEvent(blurInside);
    assert.equal(f.second.popover.hidden, false);

    const blurOutside = new Event('focusout');
    Object.defineProperty(blurOutside, 'relatedTarget', { value: f.outside });
    f.second.container.dispatchEvent(blurOutside);
    assert.equal(f.second.popover.hidden, true);
    assert.equal(f.second.trigger.getAttribute('aria-expanded'), 'false');

    cleanup();
});

test('cleanup is idempotent, closes open popover, and restores native title attribute', () => {
    const f = fixture();
    const cleanup = mount(f.root);
    assert.equal(mount(f.root), cleanup);

    f.first.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.first.popover.hidden, false);

    cleanup();
    cleanup();

    assert.equal(f.first.popover.hidden, true);
    assert.equal(f.first.trigger.getAttribute('aria-expanded'), 'false');
    assert.equal(f.first.trigger.getAttribute('title'), 'Seberapa yakin model terhadap tebakannya.');
});

test('trigger stays inline and opening a popover does not change the paragraph box', () => {
    const f = fixture();
    const cleanup = mount(f.root);

    // The component renders a <button>; the CSS contract keeps it inline with zero box.
    assert.match(f.first.trigger.constructor.name, /EventTarget|Object/);
    const declared = readTriggerCss();
    assert.equal(declared.display, 'inline');
    assert.equal(declared.appearance, 'none', 'native button chrome must be removed so the trigger keeps inline flow');
    assert.equal(declared.margin, '0');
    assert.equal(declared.padding, '0');
    assert.equal(declared.border, '0');
    assert.equal(declared['line-height'], 'inherit');
    assert.equal(declared.position, 'relative', 'relative is required for the absolute hit-area/popover');
    assert.doesNotMatch(readFileSync(new URL('../../resources/css/components.css', import.meta.url), 'utf8'), /\.hl-term__trigger:hover[^{]*\{[^}]*padding/, 'hover must not add padding');

    const before = paragraphGeometry(f);
    f.first.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.first.popover.hidden, false);
    assert.equal(paragraphGeometry(f).extraHeight, before.extraHeight, 'opening must not resize the paragraph');

    f.first.trigger.dispatchEvent(new Event('click'));
    assert.equal(f.first.popover.hidden, true);
    assert.equal(paragraphGeometry(f).extraHeight, before.extraHeight, 'closing must not resize the paragraph');

    cleanup();
});

/** Read the real CSS declarations so the test fails when the stylesheet regresses. */
function readTriggerCss() {
    const css = readFileSync(new URL('../../resources/css/components.css', import.meta.url), 'utf8');
    const rule = /\.hl-term__trigger\s*\{([^}]*)\}/.exec(css);
    assert.ok(rule, '.hl-term__trigger rule must exist');
    const declarations = {};
    for (const line of rule[1].split(';')) {
        const [property, value] = line.split(':').map(part => part?.trim());
        if (property && value) declarations[property] = value;
    }
    assert.doesNotMatch(rule[1], /min-block-size\s*:\s*(?!0)/, 'no 44px min height on an inline trigger');
    return declarations;
}

/** Paragraph box derived from the popover state only (absolute positioning must not add height). */
function paragraphGeometry(f) {
    const css = readFileSync(new URL('../../resources/css/components.css', import.meta.url), 'utf8');
    const popoverRule = /\.hl-term__popover\s*\{([^}]*)\}/.exec(css)?.[1] ?? '';
    const absolutelyPositioned = /position\s*:\s*absolute/.test(popoverRule);
    const popoverTakesSpace = f.first.popover.hidden === false && !absolutelyPositioned;
    return { open: f.first.popover.hidden === false, extraHeight: popoverTakesSpace ? 1 : 0 };
}

test('Space does not scroll but the trigger keeps native button activation; Enter is untouched', () => {
    const f = fixture();
    const cleanup = mount(f.root);

    const space = new Event('keydown', { cancelable: true });
    Object.defineProperty(space, 'key', { value: ' ' });
    f.first.trigger.dispatchEvent(space);
    assert.equal(space.defaultPrevented, true, 'Space must not scroll the page');

    const enter = new Event('keydown', { cancelable: true });
    Object.defineProperty(enter, 'key', { value: 'Enter' });
    f.first.trigger.dispatchEvent(enter);
    assert.equal(enter.defaultPrevented, false, 'Enter must keep native activation');

    cleanup();
});
