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
    for (const form of root.querySelectorAll('[data-password-hints]')) {
        const input = form.querySelector('[name="password"]');
        if (!input) continue;
        const confirmation = form.querySelector('[name="password_confirmation"]');
        const profile = form.dataset.passwordHints === 'profile';
        const indicator = form.querySelector(profile ? '#strength-container' : '#password-strength');
        const label = form.querySelector(profile ? '#strength-label' : '#ps-label');
        const match = form.querySelector('#confirm-match');
        const colors = ['var(--ds-danger-fg)', 'var(--ds-warning-fg)', profile ? 'var(--ds-warning-fg)' : 'var(--ds-info-fg)', 'var(--ds-success-fg)'];
        const labels = ['Lemah', profile ? 'Cukup' : 'Sedang', 'Kuat', 'Sangat Kuat'];
        const updateMatch = () => {
            if (!match || !confirmation) return;
            const equal = input.value === confirmation.value;
            match.style.display = confirmation.value ? 'block' : 'none';
            match.textContent = equal ? '✓ Kata sandi cocok' : '✗ Kata sandi tidak cocok';
            match.style.color = equal ? 'var(--ds-success-fg)' : 'var(--ds-danger-fg)';
        };
        const update = () => {
            const value = input.value;
            const checks = profile ? [value.length >= 8, /[A-Z]/.test(value), /[0-9]/.test(value), /[^A-Za-z0-9]/.test(value)]
                : [value.length >= 8, value.length >= 12, /[A-Z]/.test(value) && /[a-z]/.test(value), /[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)];
            const score = checks.filter(Boolean).length;
            if (indicator) indicator.style.display = value ? 'block' : 'none';
            for (let i = 1; i <= 4; i++) {
                const bar = form.querySelector(profile ? `#strength-bar-${i}` : `#ps-${i}`);
                if (bar) bar.style.background = i <= score ? colors[score - 1] : 'var(--ds-bg-subtle)';
            }
            if (label) { label.textContent = labels[score - 1] ?? ''; label.style.color = colors[score - 1] ?? 'var(--color-text-muted)'; }
            updateMatch();
        };
        scope.listen(input, 'input', update);
        if (confirmation) scope.listen(confirmation, 'input', updateMatch);
        scope.listen(form, 'reset', event => scope.timeout(() => { if (!event.defaultPrevented) update(); }, 0));
        if (match) scope.listen(form, 'submit', event => {
            if (input.value !== confirmation.value) { event.preventDefault(); updateMatch(); }
        });
        update();
    }
});
