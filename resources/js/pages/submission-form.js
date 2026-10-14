import { defineModule } from '../core/scope.js';
import { mountMediaInputs } from './submission-media.js';

export const countText = value => Array.from(value.trim()).length;
export function textError(value, minimum, maximum) {
    const length = countText(value);
    if (!length) return 'Teks informasi wajib diisi.';
    if (length < minimum) return `Teks minimal ${minimum} karakter.`;
    if (length > maximum) return `Teks maksimal ${maximum.toLocaleString('id-ID')} karakter.`;
    return '';
}

export const mount = defineModule((root, scope) => {
    mountMediaInputs(root, scope);
    const form = root.querySelector('[data-text-submission]');
    if (!form) return;
    const input = form.querySelector('[name="raw_input"]');
    const counter = form.querySelector('[data-text-count]');
    const error = form.querySelector('[data-text-error]');
    const status = form.querySelector('[data-submission-status]');
    const serverInvalid = input.getAttribute('aria-invalid');
    const message = () => textError(input.value, input.minLength, input.maxLength);
    const update = () => { counter.textContent = `${countText(input.value).toLocaleString('id-ID')} karakter`; };
    const validate = () => { const result = message(); input.setCustomValidity(result); return result; };
    const showError = () => {
        error.textContent = message();
        error.hidden = !error.textContent;
        input.setAttribute('aria-invalid', 'true');
    };
    scope.listen(input, 'invalid', showError);
    scope.listen(input, 'input', () => {
        update();
        const result = validate();
        if (!error.hidden) {
            error.textContent = result;
            error.hidden = !result;
        }
        input.setAttribute('aria-invalid', result || serverInvalid === 'true' ? 'true' : 'false');
    });
    scope.listen(form, 'submit', event => {
        if (event.defaultPrevented) return;
        if (validate()) {
            event.preventDefault();
            input.reportValidity();
            return;
        }
        // A canceled submit never announces a request. Server stages appear
        // only after the native redirect to the result/progress page.
        scope.timeout(() => {
            if (!event.defaultPrevented) status.textContent = 'Mengirim informasi. Tunggu respons server.';
        }, 0);
    });
    scope.listen(form, 'reset', event => scope.timeout(() => {
        if (event.defaultPrevented) return;
        input.setCustomValidity('');
        input.setAttribute('aria-invalid', serverInvalid);
        error.hidden = true;
        status.textContent = '';
        update();
    }, 0));
    scope.own(() => {
        // Livewire caches outerHTML, which omits a textarea's edited value.
        // Reflect only this safe text draft through the DOM text setter; never
        // put CAPTCHA/file/password values into a cached HTML attribute.
        input.defaultValue = input.value;
        input.setCustomValidity('');
        input.setAttribute('aria-invalid', serverInvalid);
        error.hidden = true;
        status.textContent = '';
        // A cached CAPTCHA answer cannot be represented as a fresh challenge.
        form.querySelector('[name="captcha_answer"]').value = '';
    });
    update();
    const summary = root.querySelector('[data-submission-errors]');
    const active = root.ownerDocument.activeElement;
    if (summary && (active === summary || active === root || active?.id === 'main-content')) {
        summary.focus({ preventScroll: true });
        summary.scrollIntoView({ block: 'start' });
    }
});
