/** Own module resources; abort requests and never persist form data. */
export function createScope({ clock = globalThis, onError = error => console.error('Hoaxlin: cleanup gagal.', error) } = {}) {
    const controller = new AbortController();
    const disposers = new Set();
    let closed = false;
    function own(dispose) {
        let released = false;
        const release = () => {
            if (released) return;
            released = true;
            disposers.delete(release);
            dispose();
        };
        if (closed) release();
        else disposers.add(release);
        return release;
    }
    function listen(target, name, handler, options) {
        target.addEventListener(name, handler, options);
        return own(() => target.removeEventListener(name, handler, options));
    }
    function timeout(callback, delay) {
        let release;
        const id = clock.setTimeout(() => { release(); if (!closed) callback(); }, delay);
        release = own(() => clock.clearTimeout(id));
        return release;
    }
    function dispose() {
        if (closed) return;
        closed = true;
        controller.abort();
        for (const release of [...disposers].reverse()) {
            try { release(); } catch (error) { onError(error); }
        }
    }
    return { signal: controller.signal, own, listen, timeout, dispose };
}

/** Guard independent callers and dispose partial mounts when setup fails. */
export function defineModule(setup) {
    const instances = new WeakMap();
    return root => {
        if (instances.has(root)) return instances.get(root);
        const scope = createScope();
        const cleanup = () => {
            if (instances.get(root) !== cleanup) return;
            scope.dispose();
            instances.delete(root);
        };
        instances.set(root, cleanup);
        try { setup(root, scope); } catch (error) { cleanup(); throw error; }
        return cleanup;
    };
}

const scrollLocks = new WeakMap();
export function lockScroll(body) {
    let lock = scrollLocks.get(body);
    if (!lock) {
        lock = { count: 0, overflow: body.style.overflow };
        scrollLocks.set(body, lock);
        body.style.overflow = 'hidden';
    }
    lock.count++;
    let released = false;
    return () => {
        if (released) return;
        released = true;
        if (--lock.count === 0) {
            body.style.overflow = lock.overflow;
            scrollLocks.delete(body);
        }
    };
}
