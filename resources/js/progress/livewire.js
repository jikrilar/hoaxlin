/** Public APIs/hooks verified against the locked Livewire 4.3.3 runtime. */
export function observeLivewire(root, scope, { onOwner, onChange, blocked, onError, onSuccess, now = Date.now }) {
    const doc = root.ownerDocument;
    const view = doc.defaultView;
    let runtime;
    let wire;
    let releaseWire;
    const currentElement = () => root.querySelector('[data-progress-state]');
    const active = () => !scope.signal.aborted && root.isConnected;

    function connect(candidate) {
        if (!active() || !candidate || candidate.$el !== currentElement()) return false;
        if (wire === candidate) return true;
        releaseWire?.();
        wire = candidate;
        onOwner(); // Abort fallback before the native poll can issue a request.
        const cleanups = [];
        if (typeof wire.$intercept === 'function') {
            cleanups.push(wire.$intercept('refreshProgress', ({ action }) => {
                if (!active() || blocked(now()) || doc.hidden || view.navigator.onLine === false) action.cancel();
            }));
        }
        // Keep in-flight native requests with Livewire. In 4.3.3 the per-message
        // unregister calls a missing WeakBag.delete; aborting a response body
        // also escapes the native fetch handler. Action/request cleanup is safe.
        if (typeof wire.$interceptRequest === 'function') {
            cleanups.push(wire.$interceptRequest('refreshProgress', ({ onError: error, onFailure, onSuccess: success, onRedirect }) => {
                error(({ response, preventDefault }) => {
                    if (!active()) return;
                    preventDefault(); // Never display a provider/debug HTML body as public UI.
                    onError(response);
                });
                onFailure(() => { if (active()) onError(null); });
                onRedirect(({ preventDefault }) => { if (active()) { preventDefault(); onError({ status: 419 }); } });
                success(() => { if (active()) onSuccess(); });
            }));
        }
        releaseWire = () => {
            for (const release of cleanups) release();
            wire = undefined;
        };
        return true;
    }
    function probe() {
        if (!active()) return false;
        attach();
        const element = currentElement();
        const id = element?.getAttribute('wire:id');
        if (!id || typeof runtime?.find !== 'function') return false;
        try { return connect(runtime.find(id)); } catch { return false; }
    }
    function attach() {
        if (runtime || typeof view.Livewire?.hook !== 'function') return;
        runtime = view.Livewire;
        scope.own(runtime.hook('component.init', ({ component }) => {
            if (component.el !== currentElement()) return;
            // component.init fires before registration; native init completes synchronously.
            Promise.resolve().then(() => { if (active()) { probe(); onChange(); } });
        }));
        scope.own(runtime.hook('directive.init', ({ el, component, directive }) => {
            if (el === currentElement() && directive.value === 'poll') connect(component.$wire);
        }));
        scope.own(runtime.hook('morphed', ({ component }) => {
            if (component.el === currentElement() && active()) { probe(); onChange(); }
        }));
    }
    scope.listen(doc, 'livewire:init', attach);
    scope.listen(doc, 'livewire:initialized', () => { probe(); onChange(); });
    scope.own(() => releaseWire?.());
    return { probe };
}
