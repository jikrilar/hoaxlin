import { defineModule } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    for (const form of root.querySelectorAll('form[data-submit-label]')) {
        let busy = false;
        const originals = new Map();
        const restore = () => {
            for (const [button, state] of originals) {
                button.replaceChildren(...state.children);
                button.disabled = state.disabled;
                if (state.busy === null) button.removeAttribute('aria-busy');
                else button.setAttribute('aria-busy', state.busy);
            }
            originals.clear();
            busy = false;
        };
        scope.own(restore);
        scope.listen(form, 'submit', event => {
            if (event.defaultPrevented) return;
            if (busy) { event.preventDefault(); return; }
            busy = true;
            // Next task runs after native validation/serialization and every
            // submit listener. Cleanup cancels it if navigation already started.
            scope.timeout(() => {
                if (event.defaultPrevented || scope.signal.aborted || !form.isConnected) { busy = false; return; }
                for (const button of form.querySelectorAll('button[type="submit"]')) {
                    if (button.disabled || button.name) continue;
                    originals.set(button, { children: [...button.childNodes], disabled: button.disabled, busy: button.getAttribute('aria-busy') });
                    button.textContent = form.dataset.submitLabel;
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
            }, 0);
        });
    }
});
