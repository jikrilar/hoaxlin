import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { createFallback, readStatus, responseError, retryAfter } from '../../resources/js/progress/fallback.js';
import { createProgress, mount } from '../../resources/js/pages/result.js';

const settle = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
const deferred = () => { let resolve; const promise = new Promise(yes => { resolve = yes; }); return { promise, resolve }; };
class Clock {
    time = 0;
    jobs = new Map();
    sequence = 0;
    Date = { now: () => this.time };
    setTimeout(callback, delay) { const id = ++this.sequence; this.jobs.set(id, { callback, at: this.time + delay }); return id; }
    clearTimeout(id) { this.jobs.delete(id); }
    async advance(delay) {
        await settle();
        const until = this.time + delay;
        for (;;) {
            const next = [...this.jobs].filter(([, job]) => job.at <= until).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            this.time = next[1].at; this.jobs.delete(next[0]); next[1].callback(); await settle();
        }
        this.time = until; await settle();
    }
}
const payload = (id = 101, extra = {}) => ({ id, status: 'processing', processing_stage: 'classifying', stage_label: 'Klasifikasi BERT', progress: 60, is_completed: false, is_failed: false, ...extra });
const response = (data = payload(), status = 200, headers = {}) => ({ ok: status >= 200 && status < 300, status, headers: new Headers({ 'Content-Type': 'application/json', ...headers }), json: async () => data });
function scheduler(options = {}) {
    const clock = new Clock(), calls = [], values = [], errors = [];
    const fetch = (url, settings) => { calls.push({ url, ...settings }); return Promise.resolve(response()); };
    const loop = createFallback({ url: '/hasil/101/status', origin: 'http://localhost', id: 101, clock, fetch,
        onStatus: value => values.push(value), onError: error => { errors.push(error); return null; }, ...options });
    return { clock, calls, values, errors, loop };
}

test('status schema rejects mismatched IDs, contradictory terminal flags and invalid progress without consuming results', () => {
    assert.deepEqual(readStatus(payload(101, { label: 'hoax', confidence_score: 1, failure_reason: 'secret' }), 101), { status: 'processing', stage: 'classifying', stageLabel: 'Klasifikasi BERT', progress: 60 });
    for (const data of [null, payload(102), payload(101, { is_completed: true }), payload(101, { is_failed: 'false' }), payload(101, { progress: NaN }), payload(101, { progress: 101 }), payload(101, { progress: -1 }), payload(101, { progress: 1.1 }), payload(101, { processing_stage: undefined })]) assert.throws(() => readStatus(data, 101));
    assert.equal(readStatus(payload(101, { progress: 0 }), '101').progress, 0);
});

test('fallback uses same-origin session credentials and rejects external/credentialed URLs', async () => {
    for (const url of ['https://other.test/status', 'http://guest:token@localhost/status', 'javascript:alert(1)']) assert.throws(() => scheduler({ url }));
    const f = scheduler(); f.loop.resume(); await f.clock.advance(2000);
    assert.equal(f.calls[0].url, 'http://localhost/hasil/101/status');
    assert.equal(f.calls[0].credentials, 'same-origin'); assert.equal(f.calls[0].headers.Accept, 'application/json'); assert.equal(f.calls[0].redirect, 'manual'); assert.equal(f.calls[0].cache, 'no-store');
    f.loop.dispose(); assert.equal(f.clock.jobs.size, 0);
});

test('slow request has one flight and the next interval starts after the response body finishes', async () => {
    const body = deferred(); const f = scheduler();
    const loop = createFallback({ url: '/status', origin: 'http://localhost', id: 101, clock: f.clock,
        fetch: (url, options) => { f.calls.push(options); return Promise.resolve({ ...response(), json: () => body.promise }); }, onStatus: data => f.values.push(data), onError: () => null });
    loop.resume(); await f.clock.advance(7000); assert.equal(f.calls.length, 1); assert.equal(f.values.length, 0);
    body.resolve(payload()); await settle(); assert.equal(f.values.length, 1);
    await f.clock.advance(1999); assert.equal(f.calls.length, 1); await f.clock.advance(1); assert.equal(f.calls.length, 2);
    loop.dispose(); assert.equal(f.clock.jobs.size, 0);
});

test('pause aborts a slow body, resume creates one loop and a late canceled response never overwrites it', async () => {
    const old = deferred(); const clock = new Clock(), calls = [], values = [];
    const loop = createFallback({ url: '/status', origin: 'http://localhost', id: 101, clock,
        fetch: (url, options) => { calls.push(options); return calls.length === 1 ? old.promise : Promise.resolve(response(payload(101, { processing_stage: 'retrieving', progress: 75 }))); }, onStatus: value => values.push(value), onError: () => null });
    loop.resume(); await clock.advance(2000); loop.pause(); assert.equal(calls[0].signal.aborted, true);
    loop.resume(0); loop.resume(0); await clock.advance(0); assert.equal(calls.length, 2); assert.equal(values[0].progress, 75);
    old.resolve(response()); await settle(); assert.equal(values.length, 1);
    loop.dispose(); await clock.advance(20000); assert.equal(calls.length, 2);
});

test('request timeout covers the body and schedules recovery without a second active request', async () => {
    const never = deferred(), clock = new Clock(), calls = [], errors = [];
    const loop = createFallback({ url: '/status', origin: 'http://localhost', id: 101, clock,
        fetch: (url, options) => { calls.push(options); return Promise.resolve({ ...response(), json: () => never.promise }); }, onStatus: () => assert.fail('Timed-out body must not render.'), onError: error => { errors.push(error); return 4000; } });
    loop.resume(); await clock.advance(12000); assert.equal(calls[0].signal.aborted, true); assert.equal(errors.length, 1);
    await clock.advance(3999); assert.equal(calls.length, 1); await clock.advance(1); assert.equal(calls.length, 2);
    loop.dispose(); never.resolve(payload()); await settle(); assert.equal(clock.jobs.size, 0);
});

test('HTTP, redirect, rate-limit, non-JSON and malformed payloads are transport failures', async () => {
    for (const value of [response({}, 403), { ...response(), type: 'opaqueredirect', status: 0 }, response({}, 429, { 'Retry-After': '30' }), { ...response(), headers: new Headers({ 'Content-Type': 'text/html' }) }, response(payload(102))]) {
        const errors = []; const f = scheduler({ fetch: async () => value, onStatus: () => assert.fail('Invalid status must not render.'), onError: error => { errors.push(error); return null; } });
        f.loop.resume(); await f.clock.advance(2000); assert.equal(errors.length, 1); assert.equal(f.clock.jobs.size, 0); f.loop.dispose();
    }
    for (const status of [401, 403, 404, 410, 419]) assert.equal(responseError(response({}, status)).fatal, true);
    assert.equal(retryAfter('12', 0), 12000); assert.equal(retryAfter('Thu, 01 Jan 1970 00:01:00 GMT', 10000), 50000); assert.equal(retryAfter('garbage', 0), 0);
});

class Node extends EventTarget {
    dataset = {}; attributes = new Map(); nodes = new Map(); textContent = ''; hidden = false; style = {}; isConnected = true;
    querySelector(selector) { return this.nodes.get(selector) ?? null; }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
}
function fixture({ id = '101', status = 'processing', fetch } = {}) {
    const clock = new Clock(), doc = new EventTarget(), view = new EventTarget(), root = new Node(), el = new Node();
    const requests = [], observers = new Set(), reloads = [];
    doc.defaultView = view; doc.hidden = false; doc.body = new Node(); view.navigator = { onLine: true }; view.location = { origin: 'http://localhost' };
    view.MutationObserver = class { constructor(callback) { this.callback = callback; } observe() { observers.add(this); } disconnect() { observers.delete(this); } };
    root.ownerDocument = doc; root.dataset = { submissionId: id, statusUrl: `/hasil/${id}/status`, initialStatus: status, progressStages: JSON.stringify(['queued', 'extracting', 'translating', 'classifying', 'retrieving', 'explaining', 'done']) };
    el.dataset.progressState = status; el.setAttribute('wire:id', `component-${id}`);
    const bar = new Node(); bar.firstElementChild = new Node(); bar.setAttribute('aria-valuenow', 60);
    for (const selector of ['h1', '[data-progress-status]', '[data-stage-label]', '[data-progress-percent]', '.hl-submission-progress__meter', '[data-progress-stage-unavailable]', '[data-progress-not-final-done]']) el.nodes.set(selector, new Node());
    el.querySelector('[data-stage-label]').textContent = 'Klasifikasi BERT'; el.nodes.set('[role="progressbar"]', bar);
    const notice = new Node(); notice.hidden = true; root.nodes.set('[data-progress-transport]', notice); root.nodes.set('[data-progress-state]', el);
    view.fetch = (url, options) => { requests.push({ url, ...options }); return fetch ? fetch(url, options) : Promise.resolve(response(payload(Number(id)))); };
    const mountProgress = () => createProgress(root, { clock, fetch: view.fetch, reload: () => reloads.push(id) });
    return { root, el, notice, doc, view, clock, requests, reloads, observers, mountProgress, mutation: () => { for (const observer of [...observers]) observer.callback(); } };
}
function runtime(f, { registered = true } = {}) {
    const hooks = new Map(), interceptors = { action: new Set(), message: new Set(), request: new Set() };
    const intercept = type => (name, callback) => { assert.equal(name, 'refreshProgress'); interceptors[type].add(callback); return () => interceptors[type].delete(callback); };
    const wire = { $el: f.el, $intercept: intercept('action'), $interceptMessage: intercept('message'), $interceptRequest: intercept('request') };
    const engine = { find: () => { if (!registered) throw new Error('Not hydrated.'); return wire; }, hook: (name, callback) => { if (!hooks.has(name)) hooks.set(name, new Set()); hooks.get(name).add(callback); return () => hooks.get(name).delete(callback); } };
    const trigger = (name, args) => { for (const callback of hooks.get(name) ?? []) callback(args); };
    const action = () => { let canceled = false; for (const callback of interceptors.action) callback({ action: { cancel: () => { canceled = true; } } }); return canceled; };
    const request = () => { const callbacks = {}; for (const callback of interceptors.request) callback(Object.fromEntries(['onError', 'onFailure', 'onSuccess', 'onRedirect', 'onResponse'].map(name => [name, handler => { callbacks[name] = handler; }]))); return callbacks; };
    f.view.Livewire = engine;
    return { engine, wire, trigger, action, request, interceptors, hooks, hydrate: () => { registered = true; trigger('directive.init', { el: f.el, component: { $wire: wire }, directive: { value: 'poll' } }); } };
}

test('wire markers and an unhydrated Livewire global never suppress fallback', async () => {
    const f = fixture(), native = runtime(f, { registered: false }); const dispose = f.mountProgress();
    await f.clock.advance(2000); assert.equal(f.requests.length, 1); assert.equal(f.el.querySelector('[role="progressbar"]').getAttribute('aria-valuenow'), '60');
    dispose(); assert.equal(f.clock.jobs.size, 0); assert.equal(f.observers.size, 0); assert.ok([...native.hooks.values()].every(h => h.size === 0));
});

test('a registered Livewire component owns polling; native request/action hooks have scoped cleanup', async () => {
    const f = fixture(), native = runtime(f); const dispose = f.mountProgress();
    await f.clock.advance(10000); assert.equal(f.requests.length, 0); assert.equal(native.action(), false);
    assert.equal(native.interceptors.action.size, 1); assert.equal(native.interceptors.request.size, 1);
    dispose(); assert.ok(Object.values(native.interceptors).every(s => s.size === 0));
});

test('late Livewire hydration aborts fallback before native polling and ignores its stale success', async () => {
    const pending = deferred(), f = fixture({ fetch: () => pending.promise }), native = runtime(f, { registered: false });
    const dispose = f.mountProgress(); await f.clock.advance(2000); assert.equal(f.requests.length, 1);
    native.hydrate(); assert.equal(f.requests[0].signal.aborted, true); assert.equal(native.action(), false);
    pending.resolve(response(payload(101, { status: 'completed', is_completed: true, progress: 100 }))); await settle();
    await f.clock.advance(20000); assert.equal(f.requests.length, 1); assert.equal(f.reloads.length, 0); dispose();
});

test('visibility and offline pause/abort fallback, and repeated resume events keep one loop', async () => {
    const f = fixture(); const dispose = f.mountProgress(); f.doc.hidden = true; f.doc.dispatchEvent(new Event('visibilitychange'));
    await f.clock.advance(20000); assert.equal(f.requests.length, 0);
    f.doc.hidden = false; f.doc.dispatchEvent(new Event('visibilitychange')); f.doc.dispatchEvent(new Event('visibilitychange')); await f.clock.advance(0); assert.equal(f.requests.length, 1);
    f.view.navigator.onLine = false; f.view.dispatchEvent(new Event('offline')); await f.clock.advance(20000); assert.equal(f.requests.length, 1); assert.ok(f.notice.textContent.includes('Tidak ada koneksi'));
    f.view.navigator.onLine = true; f.view.dispatchEvent(new Event('online')); await f.clock.advance(0); assert.equal(f.requests.length, 2); assert.equal(f.notice.hidden, true); dispose();
});

test('native hidden/offline actions are gated without registering the broken per-message cleanup API', () => {
    const f = fixture(), native = runtime(f), dispose = f.mountProgress();
    assert.equal(native.interceptors.message.size, 0);
    f.doc.hidden = true; f.doc.dispatchEvent(new Event('visibilitychange')); assert.equal(native.action(), true);
    f.doc.hidden = false; f.doc.dispatchEvent(new Event('visibilitychange')); assert.equal(native.action(), false);
    f.view.navigator.onLine = false; assert.equal(native.action(), true); dispose();
});

for (const status of [401, 403, 404, 419]) test(`HTTP ${status} halts fallback without turning the domain into failed`, async () => {
    const f = fixture({ fetch: async () => response({}, status) }), dispose = f.mountProgress();
    await f.clock.advance(20000); assert.equal(f.requests.length, 1); assert.equal(f.el.dataset.progressState, 'processing'); assert.equal(f.reloads.length, 0); assert.ok(f.notice.textContent.includes('Sesi atau akses')); dispose();
});

test('transient errors retry with bounded backoff, retain the last stage and then offer manual recovery', async () => {
    const f = fixture({ fetch: async () => ({ ...response(), headers: new Headers({ 'Content-Type': 'text/html' }) }) }), dispose = f.mountProgress();
    await f.clock.advance(2000); assert.equal(f.requests.length, 1); assert.equal(f.el.querySelector('[data-stage-label]').textContent, 'Klasifikasi BERT');
    await f.clock.advance(3999); assert.equal(f.requests.length, 1); await f.clock.advance(1); assert.equal(f.requests.length, 2);
    await f.clock.advance(8000); assert.equal(f.requests.length, 3); await f.clock.advance(60000); assert.equal(f.requests.length, 3); assert.equal(f.el.dataset.progressState, 'processing'); assert.equal(f.clock.jobs.size, 0); dispose();
});

test('429 respects Retry-After; successful recovery clears transport feedback', async () => {
    let count = 0; const f = fixture({ fetch: async () => ++count === 1 ? response({}, 429, { 'Retry-After': '30' }) : response(payload()) }), dispose = f.mountProgress();
    await f.clock.advance(31999); assert.equal(f.requests.length, 1); assert.ok(f.notice.textContent.includes('dibatasi'));
    await f.clock.advance(1); assert.equal(f.requests.length, 2); assert.equal(f.notice.hidden, true); dispose();
});

test('native access failures suppress unsafe default HTML and stop subsequent polling actions', () => {
    const f = fixture(), native = runtime(f), dispose = f.mountProgress(), callbacks = native.request(); let prevented = false;
    callbacks.onError({ response: response({}, 403), preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true); assert.equal(native.action(), true); assert.equal(f.el.dataset.progressState, 'processing');
    callbacks.onSuccess(); assert.ok(f.notice.textContent.includes('Sesi atau akses')); dispose();
});

test('native 429 gates actions until server retry time; redirect/session expiry never changes domain status', async () => {
    const f = fixture(), native = runtime(f), dispose = f.mountProgress(); const callbacks = native.request();
    callbacks.onError({ response: response({}, 429, { 'Retry-After': '30' }), preventDefault() {} }); assert.equal(native.action(), true);
    await f.clock.advance(30000); assert.equal(native.action(), false); callbacks.onSuccess(); assert.equal(f.notice.hidden, true);
    callbacks.onRedirect({ preventDefault() {} }); assert.equal(native.action(), true); assert.equal(f.el.dataset.progressState, 'processing'); dispose();
});

test('a non-JSON native response cannot expose its body or change domain status', () => {
    const f = fixture(), native = runtime(f), dispose = f.mountProgress(); let prevented = false;
    native.request().onError({ response: { ...response(), headers: new Headers({ 'Content-Type': 'text/html' }) }, preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true); assert.ok(f.notice.textContent.includes('Belum dapat memperbarui')); assert.equal(f.el.dataset.progressState, 'processing'); dispose();
});

test('real HTTP fetches remain sequential when the server takes longer than the polling interval', { timeout: 3000 }, async () => {
    let active = 0, peak = 0, received = 0, finish;
    const done = new Promise(resolve => { finish = resolve; });
    const server = createServer((request, result) => {
        received++; active++; peak = Math.max(peak, active);
        setTimeout(() => { active--; result.writeHead(200, { 'Content-Type': 'application/json' }); result.end(JSON.stringify(payload())); }, 45);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = `http://127.0.0.1:${server.address().port}`;
    let count = 0;
    const loop = createFallback({ url: '/status', origin, id: 101, interval: 5,
        onStatus: () => { if (++count === 2) { loop.dispose(); finish(); } }, onError: () => { assert.fail('Local status server must succeed.'); } });
    try { loop.resume(); await done; assert.equal(peak, 1); assert.equal(received, 2); }
    finally { loop.dispose(); server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
});

for (const status of ['completed', 'failed']) test(`${status} stops before a single terminal transition, without rendering partial labels or failed 100%`, async () => {
    const f = fixture({ fetch: async () => response(payload(101, { status, is_completed: status === 'completed', is_failed: status === 'failed', progress: 100, label: 'hoax' })) }), dispose = f.mountProgress();
    await f.clock.advance(2000); assert.deepEqual(f.reloads, ['101']); assert.equal(f.clock.jobs.size, 0); assert.equal(f.observers.size, 0);
    assert.equal(f.el.querySelector('[role="progressbar"]').getAttribute('aria-valuenow'), '60');
    const remount = f.mountProgress(); await f.clock.advance(10000); assert.equal(f.reloads.length, 1); dispose(); remount();
});

test('terminal native morph transitions each submission once; SSR failed/completed mounts never reload', () => {
    for (const id of ['101', '102']) {
        const f = fixture({ id }), native = runtime(f), dispose = f.mountProgress(); f.el.dataset.progressState = 'completed'; native.trigger('morphed', { component: { el: f.el } }); f.mutation();
        assert.deepEqual(f.reloads, [id]); assert.equal(f.observers.size, 0); dispose();
    }
    for (const status of ['failed', 'completed']) { const f = fixture({ status }), dispose = f.mountProgress(); assert.equal(f.reloads.length, 0); assert.equal(f.clock.jobs.size, 0); dispose(); }
});

test('unmount/root replacement aborts old requests and callbacks cannot overwrite a new submission', async () => {
    const pending = deferred(), f = fixture({ fetch: () => pending.promise }), dispose = f.mountProgress(); await f.clock.advance(2000);
    f.root.isConnected = false; f.mutation(); assert.equal(f.requests[0].signal.aborted, true);
    const next = fixture({ id: '102' }), cleanup = next.mountProgress(); await next.clock.advance(2000);
    pending.resolve(response(payload(101, { status: 'completed', is_completed: true, progress: 100 }))); await settle(); assert.equal(f.reloads.length, 0); assert.equal(next.el.dataset.progressState, 'processing'); dispose(); cleanup();
});

test('unknown stages are honest; zero progress and backend labels use safe text updates', async () => {
    const f = fixture({ fetch: async () => response(payload(101, { processing_stage: 'unknown<script>', stage_label: '<script>private</script>', progress: 0 })) }), dispose = f.mountProgress();
    await f.clock.advance(2000); assert.equal(f.el.querySelector('[data-stage-label]').textContent, 'Belum tersedia'); assert.equal(f.el.querySelector('[data-progress-percent]').textContent, '0%'); assert.equal(f.el.querySelector('[data-progress-stage-unavailable]').hidden, false); dispose();
    const safe = fixture({ fetch: async () => response(payload(101, { stage_label: '<script>literal</script>' })) }), cleanup = safe.mountProgress(); await safe.clock.advance(2000); assert.equal(safe.el.querySelector('[data-stage-label]').textContent, '<script>literal</script>'); cleanup();
});

test('module mount is idempotent and cleanup removes every resource before remount', async () => {
    const f = fixture(); Object.assign(f.view, { setTimeout: f.clock.setTimeout.bind(f.clock), clearTimeout: f.clock.clearTimeout.bind(f.clock), Date: f.clock.Date });
    const body = new Node(); body.querySelectorAll = () => [f.root]; const cleanup = mount(body); assert.equal(mount(body), cleanup); await f.clock.advance(2000); assert.equal(f.requests.length, 1);
    cleanup(); assert.equal(f.clock.jobs.size, 0); assert.equal(f.observers.size, 0); const again = mount(body); await f.clock.advance(2000); assert.equal(f.requests.length, 2); again();
});
