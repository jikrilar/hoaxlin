import { defineModule, lockScroll } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    const doc = root.ownerDocument;
    const view = doc.defaultView;
    const navbar = root.querySelector('#main-navbar');
    const toggle = root.querySelector('#menu-toggle');
    const mobile = root.querySelector('#mobile-menu');
    const user = root.querySelector('#user-menu-btn');
    const dropdown = root.querySelector('#user-dropdown');
    let unlock;
    function setMobile(open, focus = false) {
        if (!toggle || !mobile) return;
        mobile.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', String(open));
        root.querySelector('#menu-icon-open').style.display = open ? 'none' : 'block';
        root.querySelector('#menu-icon-close').style.display = open ? 'block' : 'none';
        if (open && !unlock) unlock = scope.own(lockScroll(root));
        if (!open && unlock) { unlock(); unlock = undefined; }
        if (focus) toggle.focus();
    }
    function setUser(open, focus = false) {
        if (!user || !dropdown) return;
        dropdown.style.display = open ? 'block' : 'none';
        user.setAttribute('aria-expanded', String(open));
        const chevron = root.querySelector('#user-menu-chevron');
        if (chevron) chevron.style.transform = open ? 'rotate(180deg)' : '';
        if (focus) user.focus();
    }
    const scroll = () => navbar?.classList.toggle('scrolled', view.scrollY > 30);
    const resize = () => {
        const small = view.innerWidth < 900;
        if (toggle) toggle.style.display = small ? 'flex' : 'none';
        const desktop = root.querySelector('#nav-desktop');
        if (desktop) desktop.style.display = small ? 'none' : 'flex';
        if (!small) setMobile(false);
    };
    if (navbar) scope.listen(view, 'scroll', scroll, { passive: true });
    scope.listen(view, 'resize', resize);
    if (toggle && mobile) scope.listen(toggle, 'click', () => setMobile(!mobile.classList.contains('open')));
    if (mobile) scope.listen(mobile, 'click', event => { if (event.target.closest('a')) setMobile(false); });
    if (user && dropdown) scope.listen(user, 'click', () => setUser(user.getAttribute('aria-expanded') !== 'true'));
    scope.listen(doc, 'click', event => {
        if (!root.querySelector('#user-menu-wrapper')?.contains(event.target)) setUser(false);
        const dismiss = event.target.closest('[data-dismiss-flash]');
        if (dismiss && root.contains(dismiss)) {
            const banner = dismiss.closest('[data-flash]');
            if (banner) { navbar?.querySelector('a')?.focus(); banner.remove(); }
        }
    });
    scope.listen(doc, 'keydown', event => {
        if (event.key !== 'Escape') return;
        if (mobile?.classList.contains('open')) setMobile(false, true);
        if (user?.getAttribute('aria-expanded') === 'true') setUser(false, true);
    });
    scope.own(() => { setMobile(false); setUser(false); });
    setMobile(false);
    setUser(false);
    resize();
    scroll();
});
