import test from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { createLifecycle, installLifecycle } from '../../resources/js/core/lifecycle.js';
import { createScope, defineModule, lockScroll } from '../../resources/js/core/scope.js';
import { mount as mountStatistics } from '../../resources/js/pages/statistics.js';
import { mount as mountForms } from '../../resources/js/components/form-submit.js';

const settle = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};
class Target extends EventTarget {
    registrations = 0;
    addEventListener(...args) { this.registrations++; super.addEventListener(...args); }
    removeEventListener(...args) { this.registrations--; super.removeEventListener(...args); }
}
function environment(readyState = 'complete') {
    const doc = new Target();
    doc.body = { isConnected: true };
    doc.readyState = readyState;
    const view = new Target();
    const options = { document: doc, window: view, onError: error => { throw error; } };
    return { doc, view, options };
}
function probe() {
    const mounts = [], cleanups = [];
    return { mounts, cleanups, module: { load: async () => ({ mount: root => { mounts.push(root); return () => cleanups.push(root); } }) } };
}

test('normal load and overlapping Livewire/pageshow hooks mount once', async () => {
    const { doc, view, options } = environment('loading');
    const p = probe();
    const lifecycle = createLifecycle([p.module], options).start();
    assert.equal(p.mounts.length, 0);
    doc.dispatchEvent(new Event('DOMContentLoaded'));
    doc.dispatchEvent(new Event('livewire:navigated'));
    view.dispatchEvent(new Event('pageshow'));
    lifecycle.start();
    await settle();
    assert.deepEqual(p.mounts, [doc.body]);
    assert.equal(doc.registrations, 3);
    assert.equal(view.registrations, 2);
    lifecycle.stop();
    lifecycle.stop();
    assert.deepEqual(p.cleanups, [doc.body]);
    assert.equal(doc.registrations + view.registrations, 0);
});

test('entry boot registers once per document, including restart after stop', async () => {
    const { doc, options } = environment();
    const p = probe();
    const first = installLifecycle([p.module], options);
    assert.equal(installLifecycle([p.module], options), first);
    await settle();
    assert.equal(p.mounts.length, 1);
    first.stop();
    installLifecycle([p.module], options);
    await settle();
    assert.equal(p.mounts.length, 2);
    assert.equal(doc.registrations, 3);
    first.stop();
});

test('navigation disposes before swap; old cleanup cannot dispose a new body', async () => {
    const { doc, options } = environment();
    const p = probe();
    const lifecycle = createLifecycle([p.module], options).start();
    await settle();
    const oldBody = doc.body;
    const oldCleanup = lifecycle.mount(oldBody);
    doc.dispatchEvent(new Event('livewire:navigating'));
    assert.deepEqual(p.cleanups, [oldBody]);
    oldBody.isConnected = false;
    doc.body = { isConnected: true };
    doc.dispatchEvent(new Event('livewire:navigated'));
    await settle();
    oldCleanup();
    assert.equal(p.cleanups.length, 1);
    lifecycle.cleanup();
    lifecycle.cleanup();
    assert.deepEqual(p.cleanups, [oldBody, doc.body]);
    lifecycle.stop();
});

test('late import is discarded after replacement and after remount of the same body', async () => {
    const { doc, options } = environment();
    const delayed = deferred();
    let mounts = 0;
    const lifecycle = createLifecycle([{ load: () => delayed.promise }], options).start();
    await settle();
    lifecycle.cleanup();
    lifecycle.mount();
    delayed.resolve({ mount: () => { mounts++; return () => {}; } });
    await settle();
    assert.equal(mounts, 1);
    lifecycle.stop();
});

test('late import never mounts a disconnected body even without navigation hooks', async () => {
    const { doc, options } = environment();
    const delayed = deferred();
    let mounts = 0;
    const lifecycle = createLifecycle([{ load: () => delayed.promise }], options).start();
    await settle();
    doc.body.isConnected = false;
    doc.body = { isConnected: true };
    delayed.resolve({ mount: () => { mounts++; return () => {}; } });
    await settle();
    assert.equal(mounts, 0);
    lifecycle.stop();
});

test('pageshow from BFCache remounts cleanly and restores owned state', async () => {
    const { doc, view, options } = environment();
    const p = probe();
    const lifecycle = createLifecycle([p.module], options).start();
    await settle();
    view.dispatchEvent(new Event('pagehide'));
    const show = new Event('pageshow');
    Object.defineProperty(show, 'persisted', { value: true });
    view.dispatchEvent(show);
    doc.dispatchEvent(new Event('livewire:navigated'));
    await settle();
    assert.equal(p.mounts.length, 2);
    assert.equal(p.cleanups.length, 1);
    lifecycle.stop();
});

test('one import/mount error does not block other SSR enhancements', async () => {
    const { options } = environment();
    const errors = [];
    const p = probe();
    const lifecycle = createLifecycle([
        { load: async () => { throw new Error('import failed'); } },
        { load: async () => ({ mount: () => { throw new Error('setup failed'); } }) },
        p.module,
    ], { ...options, onError: error => errors.push(error.message) }).start();
    await settle();
    assert.deepEqual(errors, ['import failed', 'setup failed']);
    assert.equal(p.mounts.length, 1);
    lifecycle.stop();
});

test('stale import rejection is handled without a misleading current-page error', async () => {
    const { options } = environment();
    const delayed = deferred();
    const errors = [];
    const lifecycle = createLifecycle([{ load: () => delayed.promise }], { ...options, onError: error => errors.push(error) }).start();
    await settle();
    lifecycle.cleanup();
    delayed.reject(new Error('old page'));
    await settle();
    assert.deepEqual(errors, []);
    lifecycle.stop();
});

test('module page marker prevents unnecessary imports', async () => {
    const { options } = environment();
    let imports = 0;
    const lifecycle = createLifecycle([{ matches: () => false, load: async () => { imports++; } }], options).start();
    await settle();
    assert.equal(imports, 0);
    lifecycle.stop();
});

test('resource scope removes listeners, cancels timers and revokes actual object URLs', async () => {
    const scope = createScope();
    const target = new Target();
    let calls = 0;
    scope.listen(target, 'click', () => calls++);
    const url = scope.objectURL(new Blob(['fixture']));
    assert.equal(await (await fetch(url)).text(), 'fixture');
    scope.timeout(() => calls++, 10);
    target.dispatchEvent(new Event('click'));
    scope.dispose();
    scope.dispose();
    target.dispatchEvent(new Event('click'));
    await new Promise(resolve => setTimeout(resolve, 20));
    assert.equal(calls, 1);
    assert.equal(target.registrations, 0);
    assert.equal(scope.signal.aborted, true);
    await assert.rejects(fetch(url));
});

test('cleanup aborts a real in-flight request', async () => {
    const received = deferred();
    const server = createServer((_request, response) => { received.resolve(); response.on('error', () => {}); });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const scope = createScope();
    try {
        const request = fetch(`http://127.0.0.1:${server.address().port}`, { signal: scope.signal });
        const rejected = assert.rejects(request, error => error.name === 'AbortError');
        await received.promise;
        scope.dispose();
        await rejected;
    } finally {
        scope.dispose();
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
});

test('disposer failures do not prevent other resources from being released', () => {
    const errors = [];
    const scope = createScope({ onError: error => errors.push(error.message) });
    let released = 0;
    scope.own(() => released++);
    scope.own(() => { throw new Error('fixture disposal'); });
    scope.dispose();
    assert.equal(released, 1);
    assert.deepEqual(errors, ['fixture disposal']);
});

test('module guard is idempotent and old cleanup cannot evict a new instance', () => {
    let mounts = 0, releases = 0;
    const mount = defineModule((_root, scope) => { mounts++; scope.own(() => releases++); });
    const root = {};
    const old = mount(root);
    assert.equal(mount(root), old);
    old();
    const current = mount(root);
    old();
    assert.equal(mount(root), current);
    current();
    assert.equal(mounts, 2);
    assert.equal(releases, 2);
});

test('failed partial setup disposes listeners and permits retry', () => {
    const target = new Target();
    const mount = defineModule((_root, scope) => {
        scope.listen(target, 'click', () => {});
        throw new Error('partial');
    });
    const root = {};
    assert.throws(() => mount(root), /partial/);
    assert.throws(() => mount(root), /partial/);
    assert.equal(target.registrations, 0);
});

test('overlapping scroll locks restore the original overflow once', () => {
    const body = { style: { overflow: 'auto' } };
    const nav = lockScroll(body);
    const dialog = lockScroll(body);
    nav(); nav();
    assert.equal(body.style.overflow, 'hidden');
    dialog(); dialog();
    assert.equal(body.style.overflow, 'auto');
});

test('chart instance is destroyed once on cleanup and can remount', () => {
    const charts = [];
    const canvas = { isConnected: true, dataset: { trend: JSON.stringify([{ label: 'Jan', total: 3, hoax: 1 }]) } };
    const root = {
        querySelector: selector => selector.includes('data-trend') ? canvas : null,
        ownerDocument: { documentElement: {}, defaultView: {
            getComputedStyle: () => ({ getPropertyValue: () => 'fixture-token' }),
            Chart: class {
                constructor(_canvas, config) { this.config = config; this.destroyed = 0; charts.push(this); }
                destroy() { this.destroyed++; }
            },
        } },
    };
    const dispose = mountStatistics(root);
    assert.equal(mountStatistics(root), dispose);
    assert.deepEqual(charts[0].config.data.datasets[0].data, [3]);
    dispose(); dispose();
    assert.equal(charts[0].destroyed, 1);
    mountStatistics(root)();
    assert.equal(charts.length, 2);
    assert.equal(charts[1].destroyed, 1);
});

function formFixture() {
    const nodes = [{ textContent: 'Original label' }, { tagName: 'SVG' }];
    const attrs = new Map();
    const button = {
        disabled: false, name: '', childNodes: [...nodes],
        getAttribute: name => attrs.get(name) ?? null,
        setAttribute: (name, value) => attrs.set(name, value),
        removeAttribute: name => attrs.delete(name),
        replaceChildren(...children) { this.childNodes = children; },
        set textContent(value) { this.childNodes = [{ textContent: value }]; },
    };
    const named = { ...button, name: 'action', childNodes: [] };
    const disabled = { ...button, disabled: true, childNodes: [] };
    const form = new Target();
    Object.assign(form, { isConnected: true, dataset: { submitLabel: 'Memproses...' }, querySelectorAll: () => [button, named, disabled] });
    const root = { querySelectorAll: () => [form] };
    return { form, root, button, named, disabled, nodes };
}
const submitEvent = () => new Event('submit', { cancelable: true });
const nextTask = () => new Promise(resolve => setTimeout(resolve, 10));

test('late validation cancellation keeps the native form usable', async () => {
    const { form, root, button, nodes } = formFixture();
    const cleanup = mountForms(root);
    form.addEventListener('submit', event => event.preventDefault(), { once: true });
    assert.equal(form.dispatchEvent(submitEvent()), false);
    await nextTask();
    assert.equal(button.disabled, false);
    assert.deepEqual(button.childNodes, nodes);
    assert.equal(form.dispatchEvent(submitEvent()), true);
    await nextTask();
    assert.equal(button.disabled, true);
    cleanup();
});

test('late chart library load mounts only the current live instance', () => {
    const script = new EventTarget();
    const canvas = { isConnected: true, dataset: { trend: '[]' } };
    const charts = [];
    const view = { getComputedStyle: () => ({ getPropertyValue: () => 'fixture-token' }) };
    const root = { querySelector: selector => selector.includes('data-trend') ? canvas : script, ownerDocument: { defaultView: view, documentElement: {} } };
    const Chart = class { constructor() { this.destroyed = false; charts.push(this); } destroy() { this.destroyed = true; } };
    const loaded = mountStatistics(root);
    assert.equal(charts.length, 0);
    view.Chart = Chart;
    script.dispatchEvent(new Event('load'));
    assert.equal(charts.length, 1);
    loaded();
    assert.equal(charts[0].destroyed, true);
    delete view.Chart;
    const stale = mountStatistics(root);
    stale();
    view.Chart = Chart;
    script.dispatchEvent(new Event('load'));
    assert.equal(charts.length, 1);
    const current = mountStatistics(root);
    script.dispatchEvent(new Event('load'));
    assert.equal(charts.length, 2);
    current();
    assert.equal(charts[1].destroyed, true);
});

test('double submit is blocked; cleanup restores original nodes and attributes', async () => {
    const { form, root, button, named, disabled, nodes } = formFixture();
    const cleanup = mountForms(root);
    assert.equal(mountForms(root), cleanup);
    assert.equal(form.dispatchEvent(submitEvent()), true);
    assert.equal(form.dispatchEvent(submitEvent()), false);
    await nextTask();
    assert.equal(button.disabled, true);
    assert.equal(button.getAttribute('aria-busy'), 'true');
    assert.equal(button.childNodes[0].textContent, 'Memproses...');
    assert.equal(named.disabled, false);
    assert.equal(disabled.disabled, true);
    cleanup(); cleanup();
    assert.deepEqual(button.childNodes, nodes);
    assert.equal(button.disabled, false);
    assert.equal(button.getAttribute('aria-busy'), null);
    const remount = mountForms(root);
    assert.equal(form.dispatchEvent(submitEvent()), true);
    remount();
    await nextTask();
    assert.equal(button.disabled, false);
    assert.deepEqual(button.childNodes, nodes);
});

test('native reset releases a busy form and allows another submission', async () => {
    const { form, root, button, nodes } = formFixture();
    const cleanup = mountForms(root);
    form.dispatchEvent(submitEvent());
    await nextTask();
    assert.equal(button.disabled, true);
    form.dispatchEvent(new Event('reset', { cancelable: true }));
    await nextTask();
    assert.equal(button.disabled, false);
    assert.deepEqual(button.childNodes, nodes);
    assert.equal(form.dispatchEvent(submitEvent()), true);
    cleanup();
});

test('cancelled reset retains the double-submit guard', async () => {
    const { form, root, button } = formFixture();
    const cleanup = mountForms(root);
    form.dispatchEvent(submitEvent());
    await nextTask();
    form.addEventListener('reset', event => event.preventDefault());
    form.dispatchEvent(new Event('reset', { cancelable: true }));
    await nextTask();
    assert.equal(button.disabled, true);
    assert.equal(form.dispatchEvent(submitEvent()), false);
    cleanup();
});
