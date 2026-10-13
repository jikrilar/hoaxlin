import test from 'node:test';
import assert from 'node:assert/strict';
import { mount } from '../../resources/js/components/password.js';

// Identifiers are ordinary field ids; real browser coverage exercises CSS.escape.
globalThis.CSS = { escape: value => value };
const settle = () => new Promise(resolve => setTimeout(resolve, 10));
function fixture(type = 'password') {
    const form = new EventTarget();
    const input = { id: 'password', type, value: 'autofilled-secret', name: 'password', autocomplete: 'current-password', form };
    const confirmation = { type: 'password', value: 'another-secret' };
    const attributes = new Map();
    const button = Object.assign(new EventTarget(), { hidden: true, dataset: { passwordToggle: 'password' }, setAttribute: (name, value) => attributes.set(name, value) });
    const root = {
        querySelector: () => input,
        querySelectorAll: selector => selector === 'input[type="password"]' ? [input, confirmation].filter(n => n.type === 'password')
            : selector === '[data-password-toggle]' ? [button] : [],
    };
    return { root, form, input, confirmation, button, attributes };
}

test('toggle preserves autofill and native attributes; cleanup clears both password fields', () => {
    const { root, input, confirmation, button, attributes } = fixture();
    const cleanup = mount(root);
    assert.equal(mount(root), cleanup);
    assert.equal(input.value, 'autofilled-secret');
    assert.equal(button.hidden, false);
    assert.equal(attributes.get('aria-controls'), input.id);
    assert.equal(attributes.get('aria-pressed'), 'false');
    button.dispatchEvent(new Event('click'));
    assert.equal(input.type, 'text');
    assert.equal(input.value, 'autofilled-secret');
    assert.equal(input.name, 'password');
    assert.equal(input.autocomplete, 'current-password');
    assert.equal(attributes.get('aria-label'), 'Sembunyikan kata sandi');
    assert.equal(attributes.get('aria-pressed'), 'true');
    cleanup(); cleanup();
    assert.equal(input.type, 'password');
    assert.equal(input.value, '');
    assert.equal(confirmation.value, '');
    assert.equal(button.hidden, true);
    assert.equal(attributes.get('aria-pressed'), 'false');
    button.dispatchEvent(new Event('click'));
    assert.equal(input.type, 'password');
    const remount = mount(root);
    button.dispatchEvent(new Event('click'));
    assert.equal(input.type, 'text');
    remount();
});

test('native reset masks a visible password without consuming the reset event', async () => {
    const { root, form, input, button, attributes } = fixture();
    const cleanup = mount(root);
    button.dispatchEvent(new Event('click'));
    assert.equal(form.dispatchEvent(new Event('reset', { cancelable: true })), true);
    await settle();
    assert.equal(input.type, 'password');
    assert.equal(attributes.get('aria-pressed'), 'false');
    cleanup();
});

test('cancelled reset keeps visibility state and cleanup cancels pending reset work', async () => {
    const { root, form, input, button } = fixture();
    const cleanup = mount(root);
    button.dispatchEvent(new Event('click'));
    form.addEventListener('reset', event => event.preventDefault(), { once: true });
    assert.equal(form.dispatchEvent(new Event('reset', { cancelable: true })), false);
    await settle();
    assert.equal(input.type, 'text');
    form.dispatchEvent(new Event('reset'));
    cleanup();
    input.type = 'text';
    await settle();
    assert.equal(input.type, 'text');
});

test('a toggle cannot expose or clear a non-password field', () => {
    const { root, input, button } = fixture('email');
    const cleanup = mount(root);
    button.dispatchEvent(new Event('click'));
    assert.equal(button.hidden, true);
    cleanup();
    assert.equal(input.type, 'email');
    assert.equal(input.value, 'autofilled-secret');
});
