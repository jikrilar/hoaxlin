import { defineModule, lockScroll } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    for (const dialog of root.querySelectorAll('dialog[data-dialog]')) {
        if (typeof dialog.showModal !== 'function') continue;
        const buttons = [...root.querySelectorAll(`[data-dialog-open="${CSS.escape(dialog.id)}"]`)];
        const fallback = dialog.dataset.dialogFallback
            ? root.querySelector(`#${CSS.escape(dialog.dataset.dialogFallback)}`) : null;
        const panel = fallback?.querySelector('[data-dialog-panel]');
        if (dialog.dataset.dialogFallback && !panel) continue;
        let trigger;
        let unlock;
        let opened = false;
        const release = () => {
            if (!opened) return;
            opened = false;
            if (unlock) { unlock(); unlock = undefined; }
            if (dialog.hasAttribute('data-dialog-reset-on-close')) {
                for (const form of dialog.querySelectorAll('form')) form.reset();
            }
            if (trigger?.isConnected && !scope.signal.aborted) trigger.focus();
        };
        const close = () => { dialog.close(); release(); };
        if (panel) {
            const next = panel.nextSibling;
            const fallbackHidden = fallback.hidden;
            const controls = [...buttons, ...panel.querySelectorAll('[data-dialog-close]')];
            const hidden = controls.map(control => control.hidden);
            scope.own(() => {
                fallback.insertBefore(panel, next);
                fallback.hidden = fallbackHidden;
                controls.forEach((control, index) => { control.hidden = hidden[index]; });
            });
            dialog.append(panel);
            fallback.hidden = true;
            controls.forEach(control => { control.hidden = false; });
        }
        const open = button => {
            if (dialog.open) return;
            trigger = button;
            dialog.showModal();
            opened = true;
            unlock = scope.own(lockScroll(root));
            dialog.querySelector('[autofocus]')?.focus();
        };
        for (const button of buttons) {
            scope.listen(button, 'click', () => open(button));
        }
        scope.listen(dialog, 'click', event => {
            if (event.target === dialog || event.target.closest('[data-dialog-close]')) close();
        });
        scope.listen(dialog, 'cancel', event => { event.preventDefault(); close(); });
        scope.listen(dialog, 'keydown', event => {
            if (event.key !== 'Tab' || !dialog.open) return;
            const controls = [...dialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
                .filter(control => !control.disabled && control.tabIndex >= 0 && control.getClientRects().length && !control.closest('[hidden], [inert]'));
            const first = controls[0];
            const last = controls.at(-1);
            const active = root.ownerDocument.activeElement;
            if ((event.shiftKey && active === first) || (!event.shiftKey && active === last)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
            }
        });
        scope.listen(dialog, 'close', () => {
            // A queued close event may arrive after the dialog was reopened.
            if (!dialog.open) release();
        });
        scope.own(close);
        if (dialog.hasAttribute('data-dialog-auto-open') && buttons[0]) open(buttons[0]);
    }
});
