import { defineModule } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    const doc = root.ownerDocument;
    const header = root.querySelector('[data-navigation]');
    const panel = header?.querySelector('[data-navigation-panel]');
    const toggle = header?.querySelector('[data-mobile-toggle]');
    const account = header?.querySelector('[data-account]');
    const summary = account?.querySelector('summary');
    const media = doc.defaultView.matchMedia('(max-width: 56.25rem)');
    let expanded = false;

    for (const button of root.querySelectorAll('[data-dismiss-flash]')) {
        button.hidden = false;
        scope.own(() => { button.hidden = true; });
    }

    const setAccount = (open, focus = false) => {
        if (!account) return;
        account.open = open;
        summary.setAttribute('aria-expanded', String(open));
        if (focus) summary.focus();
    };
    const sync = () => {
        if (!panel || !toggle) return;
        toggle.hidden = !media.matches;
        panel.hidden = media.matches && !expanded;
        toggle.setAttribute('aria-expanded', String(media.matches && expanded));
        if (panel.hidden && panel.contains(doc.activeElement)) toggle.focus();
        if (toggle.hidden && doc.activeElement === toggle) header.querySelector('[data-brand]').focus();
    };
    const close = (focus = false) => {
        expanded = false;
        setAccount(false);
        sync();
        if (focus && !toggle.hidden) toggle.focus();
    };
    if (header && panel && toggle) {
        header.setAttribute('data-navigation-ready', '');
        scope.listen(toggle, 'click', () => { expanded = !expanded; if (!expanded) setAccount(false); sync(); });
        scope.listen(media, 'change', () => close());
        scope.listen(panel, 'click', event => { if (event.target.closest('a')) close(); });
        scope.listen(header, 'focusout', event => { if (event.relatedTarget && !header.contains(event.relatedTarget)) close(); });
        sync();
        scope.own(() => {
            panel.hidden = false;
            toggle.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            header.removeAttribute('data-navigation-ready');
        });
    }
    if (account) {
        scope.listen(account, 'toggle', () => summary.setAttribute('aria-expanded', String(account.open)));
        scope.listen(account, 'focusout', event => { if (event.relatedTarget && !account.contains(event.relatedTarget)) setAccount(false); });
        setAccount(false);
        scope.own(() => { account.open = false; summary.removeAttribute('aria-expanded'); });
    }
    scope.listen(doc, 'keydown', event => {
        if (event.key !== 'Escape') return;
        if (account?.open) { event.preventDefault(); setAccount(false, true); }
        else if (expanded) { event.preventDefault(); close(true); }
    });
    scope.listen(doc, 'click', event => {
        if (account?.open && !account.contains(event.target)) setAccount(false);
        if (expanded && !header.contains(event.target)) close();
        const dismiss = event.target.closest?.('[data-dismiss-flash]');
        if (!dismiss || !root.contains(dismiss)) return;
        const message = dismiss.closest('[data-flash]');
        if (!message) return;
        const list = message.parentElement;
        message.remove();
        const next = list.querySelector('[data-dismiss-flash]');
        (next ?? root.querySelector('#main-content'))?.focus();
        if (!next) list.remove();
    });
});
