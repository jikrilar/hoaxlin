import test from 'node:test';
import assert from 'node:assert/strict';
import { mount } from '../../resources/js/components/dialog.js';

globalThis.CSS = { escape: value => value };
function fixture({ auto = false, supported = true } = {}) {
    const document = { activeElement: null };
    const closeEvents = [];
    const control = (id, hidden = false) => Object.assign(new EventTarget(), {
        id, hidden, disabled: false, tabIndex: 0, isConnected: true,
        focus() { document.activeElement = this; },
        getClientRects() { return this.hidden ? [] : [{}]; },
        closest(selector) { return selector === '[data-dialog-close]' && id === 'cancel' ? this : null; },
    });
    const trigger = control('trigger', true), cancel = control('cancel', true);
    const password = control('password'), confirm = control('confirm'), error = control('error');
    password.value = ''; error.tabIndex = -1;
    const form = { resets: 0, reset() { this.resets++; password.value = ''; } };
    const panel = { nextSibling: null, querySelectorAll: () => [cancel] };
    const fallback = {
        hidden: false, panel,
        querySelector: () => fallback.panel,
        insertBefore(value) { this.panel = value; dialog.panel = null; },
    };
    const attributes = new Set(['data-dialog-reset-on-close', ...(auto ? ['data-dialog-auto-open'] : [])]);
    const dialog = Object.assign(new EventTarget(), {
        id: 'dialog', dataset: { dialogFallback: 'fallback' }, panel: null, open: false,
        append(value) { this.panel = value; fallback.panel = null; },
        hasAttribute: name => attributes.has(name),
        querySelector: () => auto ? error : cancel,
        querySelectorAll: selector => selector === 'form' ? [form] : [password, cancel, confirm],
        showModal() { this.open = true; },
        close() { if (this.open) { this.open = false; closeEvents.push(() => this.dispatchEvent(new Event('close'))); } },
    });
    if (!supported) dialog.showModal = undefined;
    const root = {
        style: { overflow: 'auto' }, ownerDocument: document,
        querySelector: () => fallback,
        querySelectorAll: selector => selector === 'dialog[data-dialog]' ? [dialog] : [trigger],
    };
    const click = target => { const e = new Event('click'); Object.defineProperty(e, 'target', { value: target }); dialog.dispatchEvent(e); };
    const flush = () => { for (const event of closeEvents.splice(0)) event(); };
    return { root, trigger, cancel, password, confirm, error, dialog, panel, fallback, form, document, click, flush };
}

test('dialog moves one native form, mounts once and restores the fallback on cleanup', () => {
    const f = fixture(); const cleanup = mount(f.root);
    assert.equal(mount(f.root), cleanup);
    assert.equal(f.dialog.panel, f.panel); assert.equal(f.fallback.hidden, true);
    assert.equal(f.trigger.hidden, false); assert.equal(f.cancel.hidden, false);
    cleanup(); cleanup();
    assert.equal(f.fallback.panel, f.panel); assert.equal(f.fallback.hidden, false);
    assert.equal(f.trigger.hidden, true); assert.equal(f.cancel.hidden, true);
    f.trigger.dispatchEvent(new Event('click')); assert.equal(f.dialog.open, false);
});

test('Escape and cancel clear the confirmation password, unlock scroll and return focus', () => {
    const f = fixture(); const cleanup = mount(f.root);
    for (const method of ['escape', 'cancel']) {
        f.trigger.dispatchEvent(new Event('click'));
        assert.equal(f.document.activeElement, f.cancel); assert.equal(f.root.style.overflow, 'hidden');
        f.password.value = 'fixture-secret';
        if (method === 'escape') {
            const e = new Event('cancel', { cancelable: true }); f.dialog.dispatchEvent(e); assert.equal(e.defaultPrevented, true);
        } else f.click(f.cancel);
        assert.equal(f.dialog.open, false); assert.equal(f.password.value, '');
        assert.equal(f.root.style.overflow, 'auto'); assert.equal(f.document.activeElement, f.trigger);
        const resets = f.form.resets; f.flush(); assert.equal(f.form.resets, resets);
    }
    cleanup();
});

test('a queued close after reopen cannot reset the new form or release its scroll lock', () => {
    const f = fixture(); const cleanup = mount(f.root);
    f.trigger.dispatchEvent(new Event('click')); f.click(f.cancel);
    f.trigger.dispatchEvent(new Event('click')); f.password.value = 'new-fixture-secret';
    f.flush(); assert.equal(f.dialog.open, true); assert.equal(f.password.value, 'new-fixture-secret');
    assert.equal(f.root.style.overflow, 'hidden');
    f.dialog.close(); f.flush(); assert.equal(f.password.value, ''); assert.equal(f.root.style.overflow, 'auto');
    cleanup();
});

test('server deletion errors auto-open with error focus and keyboard wrap in both directions', () => {
    const f = fixture({ auto: true }); const cleanup = mount(f.root);
    assert.equal(f.dialog.open, true); assert.equal(f.document.activeElement, f.error);
    for (const [active, shiftKey, expected] of [[f.confirm, false, f.password], [f.password, true, f.confirm]]) {
        active.focus(); const e = new Event('keydown', { cancelable: true });
        Object.assign(e, { key: 'Tab', shiftKey }); f.dialog.dispatchEvent(e);
        assert.equal(e.defaultPrevented, true); assert.equal(f.document.activeElement, expected);
    }
    f.password.value = 'fixture-secret'; cleanup(); f.flush();
    assert.equal(f.password.value, ''); assert.equal(f.root.style.overflow, 'auto');
    assert.equal(f.fallback.panel, f.panel); assert.equal(f.dialog.open, false);
});

test('unsupported modal API leaves the native disclosure and form usable', () => {
    const f = fixture({ supported: false }); const cleanup = mount(f.root);
    assert.equal(f.fallback.hidden, false); assert.equal(f.fallback.panel, f.panel);
    assert.equal(f.trigger.hidden, true); assert.equal(f.dialog.panel, null);
    cleanup();
});
