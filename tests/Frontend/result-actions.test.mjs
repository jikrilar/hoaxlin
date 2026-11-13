import test from 'node:test';
import assert from 'node:assert/strict';
import { sanitizeSummary, mountCopy, mountCommentReveal, SUMMARY_MAX } from '../../resources/js/pages/result-actions.js';

test('summary payload keeps label, description and date but strips ids and links', () => {
    const raw = [
        'Hoaxlin — ringkasan hasil',
        'Prediksi model: Hoaks. Pola teks ini mirip dengan hoaks.',
        'ID 42',
        'Dikirim: 12 Nov 2026',
        'Buka https://hoaxlin.test/hasil/42 untuk detail',
    ].join('\n');
    const clean = sanitizeSummary(raw);

    assert.ok(clean.includes('Prediksi model: Hoaks'));
    assert.ok(clean.includes('Dikirim: 12 Nov 2026'));
    assert.ok(!/^\s*ID\s*:?\s*\d+/im.test(clean), 'id lines are dropped');
    assert.ok(!clean.includes('https://'), 'URLs are stripped');
    assert.ok(!clean.includes('/hasil/'), 'result paths are stripped');
});

test('summary payload is bounded', () => {
    assert.equal(sanitizeSummary('a'.repeat(SUMMARY_MAX + 50)).length, SUMMARY_MAX);
});

function copyFixture({ clipboard } = {}) {
    const listeners = [];
    const status = { textContent: '' };
    const cta = Object.assign(new EventTarget(), {
        hidden: true,
        closest: () => root,
        addEventListener: EventTarget.prototype.addEventListener.bind(Object.create(EventTarget.prototype)),
        removeEventListener: () => {},
    });
    const template = { content: { textContent: 'Prediksi model: Valid.\nDikirim: 12 Nov 2026' }, textContent: '' };
    const root = { querySelector: selector => selector.startsWith('template') ? template : (selector === '[data-copy-summary-status]' ? status : null) };
    const scope = {
        listen: (target, type, handler) => listeners.push([target, type, handler]),
        own: () => {},
    };
    // Node exposes `navigator` as a getter-only global; define an override instead.
    Object.defineProperty(globalThis, 'navigator', { value: clipboard === undefined ? {} : { clipboard }, configurable: true });
    return { root, cta, status, scope, listeners };
}

test('copy button reveals itself, copies sanitized text and announces success', async () => {
    let copied = null;
    const f = copyFixture({ clipboard: { writeText: async value => { copied = value; } } });
    mountCopy(f.scope, f.cta);
    assert.equal(f.cta.hidden, false, 'button is only shown once JS is ready');

    const [[, , handler]] = f.listeners.filter(([, type]) => type === 'click');
    await handler();
    assert.equal(copied, 'Prediksi model: Valid.\nDikirim: 12 Nov 2026');
    assert.equal(f.status.textContent, 'Ringkasan disalin.');
});

test('missing clipboard support degrades to a manual instruction instead of failing', async () => {
    const f = copyFixture();
    mountCopy(f.scope, f.cta);
    const [[, , handler]] = f.listeners.filter(([, type]) => type === 'click');
    await handler();
    assert.match(f.status.textContent, /Tidak dapat menyalin otomatis/);
});

test('comment field is only revealed after a rating choice', () => {
    const choices = ['1', '0'].map(value => Object.assign(new EventTarget(), { value, checked: false }));
    const comment = { dataset: {} };
    const section = {
        querySelectorAll: () => choices,
        querySelector: selector => selector === '[data-feedback-comment]' ? comment : null,
    };
    const scope = { listen: (target, type, handler) => target.addEventListener(type, handler), own: () => {} };
    mountCommentReveal(scope, section);
    assert.equal(comment.dataset.revealed, 'false');

    choices[1].checked = true;
    choices[1].dispatchEvent(new Event('change'));
    assert.equal(comment.dataset.revealed, '', 'revealed once a choice exists');
});
