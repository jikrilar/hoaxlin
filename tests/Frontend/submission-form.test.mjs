import test from 'node:test';
import assert from 'node:assert/strict';
import { countText, textError, mount } from '../../resources/js/pages/submission-form.js';

const settle = () => new Promise(resolve => setTimeout(resolve, 10));
function fixture(value = '') {
    const attrs = new Map([['aria-invalid', 'false']]);
    const input = Object.assign(new EventTarget(), {
        value, minLength: 50, maxLength: 50000, custom: '',
        getAttribute: key => attrs.get(key), setAttribute: (key, value) => attrs.set(key, value),
        setCustomValidity(value) { this.custom = value; },
        reportValidity() { if (this.custom) this.dispatchEvent(new Event('invalid')); return !this.custom; },
    });
    const counter = { textContent: '' }, error = { hidden: true, textContent: '' }, status = { textContent: '' }, captcha = { value: '5' };
    const nodes = { '[name="raw_input"]': input, '[data-text-count]': counter, '[data-text-error]': error, '[data-submission-status]': status, '[name="captcha_answer"]': captcha };
    const form = Object.assign(new EventTarget(), { querySelector: selector => nodes[selector] });
    const root = { querySelectorAll: () => [], querySelector: selector => selector === '[data-text-submission]' ? form : null, ownerDocument: { activeElement: null } };
    return { root, form, input, counter, error, status, captcha };
}
const submit = () => new Event('submit', { cancelable: true });

test('normalized Unicode character boundaries match server text policy', () => {
    assert.equal(countText('  informasi 😀  '), 11);
    assert.equal(textError(' ', 50, 50000), 'Teks informasi wajib diisi.');
    assert.equal(textError('a'.repeat(49), 50, 50000), 'Teks minimal 50 karakter.');
    assert.equal(textError(' a'.padEnd(50, ' '), 50, 50000), 'Teks minimal 50 karakter.');
    assert.equal(textError('a'.repeat(50), 50, 50000), '');
    assert.equal(textError('a'.repeat(50000), 50, 50000), '');
    assert.equal(textError('a'.repeat(50001), 50, 50000), 'Teks maksimal 50.000 karakter.');
});

test('invalid submit exposes inline recovery without announcing a request or erasing text', async () => {
    const f = fixture('teks pendek');
    const cleanup = mount(f.root);
    assert.equal(f.counter.textContent, '11 karakter');
    assert.equal(f.input.custom, ''); // Initial/autofilled values are validated on submit.
    assert.equal(f.form.dispatchEvent(submit()), false);
    await settle();
    assert.equal(f.error.hidden, false);
    assert.equal(f.error.textContent, 'Teks minimal 50 karakter.');
    assert.equal(f.input.value, 'teks pendek');
    assert.equal(f.status.textContent, '');
    f.input.value = 'a'.repeat(50);
    f.input.dispatchEvent(new Event('input'));
    assert.equal(f.input.custom, '');
    assert.equal(f.error.hidden, true);
    assert.equal(f.input.getAttribute('aria-invalid'), 'false');
    assert.equal(f.form.dispatchEvent(submit()), true);
    await settle();
    assert.equal(f.status.textContent, 'Mengirim informasi. Tunggu respons server.');
    cleanup();
});

test('late submit cancellation never announces progress', async () => {
    const f = fixture('a'.repeat(50));
    const cleanup = mount(f.root);
    f.form.addEventListener('submit', event => event.preventDefault());
    assert.equal(f.form.dispatchEvent(submit()), false);
    await settle();
    assert.equal(f.status.textContent, '');
    assert.equal(f.input.value.length, 50);
    cleanup();
});

test('cleanup is idempotent, clears stale CAPTCHA and owned validity, and preserves safe draft', async () => {
    const f = fixture('a'.repeat(50));
    const cleanup = mount(f.root);
    assert.equal(mount(f.root), cleanup);
    f.form.dispatchEvent(submit());
    cleanup(); cleanup();
    await settle();
    assert.equal(f.captcha.value, '');
    assert.equal(f.input.value.length, 50);
    assert.equal(f.input.defaultValue, 'a'.repeat(50));
    assert.equal(f.input.custom, '');
    assert.equal(f.status.textContent, '');
    f.input.value = 'short';
    f.input.dispatchEvent(new Event('input'));
    assert.equal(f.counter.textContent, '50 karakter');
    assert.equal(f.input.custom, '');
    const remount = mount(f.root);
    assert.equal(f.counter.textContent, '5 karakter');
    remount();
});

test('reset clears client feedback; canceled reset retains it', async () => {
    const f = fixture('short');
    const cleanup = mount(f.root);
    f.form.dispatchEvent(submit());
    f.form.addEventListener('reset', event => event.preventDefault(), { once: true });
    f.form.dispatchEvent(new Event('reset', { cancelable: true }));
    await settle();
    assert.equal(f.error.hidden, false);
    f.form.dispatchEvent(new Event('reset', { cancelable: true }));
    f.input.value = '';
    await settle();
    assert.equal(f.error.hidden, true);
    assert.equal(f.input.custom, '');
    assert.equal(f.counter.textContent, '0 karakter');
    cleanup();
});
