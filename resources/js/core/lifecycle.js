const installations = new WeakMap();

/** Mount once on the current body, including overlapping initial hooks. */
export function createLifecycle(modules, { document: doc = document, window: view = window, onError = error => console.error('Hoaxlin: interaksi tidak dapat dimuat.', error) } = {}) {
    let current;
    let started = false;

    function cleanup() {
        if (!current) return;
        const previous = current;
        current = undefined; // Invalidate pending imports before disposal.
        for (const dispose of previous.disposers.reverse()) {
            try { dispose(); } catch (error) { onError(error); }
        }
    }

    function mount(root = doc.body) {
        if (!root || !root.isConnected) return () => {};
        if (current?.root === root) return current.dispose;
        cleanup();
        const instance = { root, disposers: [], dispose: () => { if (current === instance) cleanup(); } };
        current = instance;
        const active = () => current === instance && root.isConnected && doc.body === root;
        for (const module of modules) {
            Promise.resolve().then(() => {
                if (active() && (!module.matches || module.matches(root))) return module.load();
            }).then(loaded => {
                if (!loaded || !active()) return;
                const dispose = loaded.mount(root);
                if (typeof dispose !== 'function') throw new TypeError('mount(root) harus mengembalikan cleanup.');
                if (active()) instance.disposers.push(dispose);
                else dispose();
            }).catch(error => { if (active()) onError(error); });
        }
        return instance.dispose;
    }

    const ready = () => mount();
    const restore = event => { if (event.persisted) cleanup(); ready(); };
    const documentEvents = [['DOMContentLoaded', ready], ['livewire:navigating', cleanup], ['livewire:navigated', ready]];
    const windowEvents = [['pagehide', cleanup], ['pageshow', restore]];
    function start() {
        if (started) return api;
        started = true;
        for (const [name, handler] of documentEvents) doc.addEventListener(name, handler);
        for (const [name, handler] of windowEvents) view.addEventListener(name, handler);
        if (doc.readyState !== 'loading') ready();
        return api;
    }
    function stop() {
        if (!started) return;
        started = false;
        for (const [name, handler] of documentEvents) doc.removeEventListener(name, handler);
        for (const [name, handler] of windowEvents) view.removeEventListener(name, handler);
        cleanup();
    }
    const api = { mount, cleanup, start, stop };
    return api;
}

/** One registration per document, without a new browser global. */
export function installLifecycle(modules, options = {}) {
    const doc = options.document ?? document;
    if (!installations.has(doc)) installations.set(doc, createLifecycle(modules, options));
    return installations.get(doc).start();
}
