import { defineModule } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    const doc = root.ownerDocument;
    const terms = [];
    let active = null;

    const setOpen = (item, open, restoreFocus = false) => {
        if (!item) return;
        item.popover.hidden = !open;
        item.trigger.setAttribute('aria-expanded', String(open));
        if (open) {
            const view = doc.defaultView;
            if (view && typeof item.popover.getBoundingClientRect === 'function') {
                delete item.container.dataset.termAlign;
                const rect = item.popover.getBoundingClientRect();
                if (rect.right > view.innerWidth - 16) {
                    item.container.dataset.termAlign = 'end';
                }
            }
            active = item;
        } else if (active === item) {
            delete item.container.dataset.termAlign;
            active = null;
        }
        if (!open && restoreFocus) {
            item.trigger.focus();
        }
    };

    for (const container of root.querySelectorAll('[data-term]')) {
        const trigger = container.querySelector('[data-term-trigger]');
        const popover = container.querySelector('[data-term-popover]');
        if (!trigger || !popover) continue;

        const originalTitle = trigger.getAttribute('title');
        if (originalTitle !== null) {
            trigger.removeAttribute('title');
        }

        const item = { container, trigger, popover, originalTitle };
        terms.push(item);

        setOpen(item, false);

        scope.listen(trigger, 'click', () => {
            const nextState = popover.hidden;
            if (active && active !== item) {
                setOpen(active, false);
            }
            setOpen(item, nextState);
        });

        // Space must not scroll, but it must still activate the native button; Enter is left alone.
        scope.listen(trigger, 'keydown', event => {
            if (event.key === ' ' && !event.repeat) event.preventDefault();
        });

        scope.listen(container, 'focusout', event => {
            if (active === item && event.relatedTarget && !container.contains(event.relatedTarget)) {
                setOpen(item, false);
            }
        });

        scope.own(() => {
            setOpen(item, false);
            if (originalTitle !== null) {
                trigger.setAttribute('title', originalTitle);
            }
        });
    }

    if (terms.length === 0) return;

    scope.listen(doc, 'keydown', event => {
        if (event.key !== 'Escape' || !active) return;
        event.preventDefault();
        setOpen(active, false, true);
    });

    scope.listen(doc, 'click', event => {
        if (!active) return;
        if (event.target && !active.container.contains(event.target)) {
            setOpen(active, false);
        }
    });
});
