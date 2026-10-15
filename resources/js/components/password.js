import { defineModule } from '../core/scope.js';

export const mount = defineModule((root, scope) => {
    // Keep browser/password-manager autofill on mount. Clear secrets only when
    // leaving the page so BFCache/Livewire snapshots cannot restore them.
    for (const input of root.querySelectorAll('input[type="password"]')) {
        scope.own(() => { input.type = 'password'; input.value = ''; });
    }
    for (const button of root.querySelectorAll('[data-password-toggle]')) {
        const input = root.querySelector(`#${CSS.escape(button.dataset.passwordToggle)}`);
        if (!input || input.type !== 'password') continue;
        const update = () => {
            const visible = input.type === 'text';
            button.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-controls', input.id);
        };
        const hidden = button.hidden;
        button.hidden = false;
        scope.listen(button, 'click', () => { input.type = input.type === 'password' ? 'text' : 'password'; update(); });
        if (input.form) scope.listen(input.form, 'reset', event => scope.timeout(() => {
            if (!event.defaultPrevented) { input.type = 'password'; update(); }
        }, 0));
        scope.own(() => { input.type = 'password'; button.hidden = hidden; update(); });
        update();
    }
});
