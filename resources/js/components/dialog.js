import { defineModule, lockScroll } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    for (const dialog of root.querySelectorAll('dialog[data-dialog]')) {
        let trigger;
        let unlock;
        const close = () => {
            dialog.close();
            if (unlock) { unlock(); unlock = undefined; }
            if (trigger?.isConnected && !scope.signal.aborted) trigger.focus();
        };
        for (const button of root.querySelectorAll(`[data-dialog-open="${CSS.escape(dialog.id)}"]`)) {
            scope.listen(button, 'click', () => {
                if (dialog.open) return;
                trigger = button;
                dialog.showModal();
                unlock = scope.own(lockScroll(root));
            });
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
            if (!dialog.open && unlock) { unlock(); unlock = undefined; }
        });
        scope.own(close);
    }
});
