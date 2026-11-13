/**
 * Result-page actions (T2.7).
 *
 * The summary text is server-rendered in a <template data-summary-source> so the
 * clipboard payload never comes from the URL or the DOM around the result. The
 * button only appears once this module runs; without JS the text stays readable
 * in the page itself.
 */

export const SUMMARY_MAX = 1000;

/** Keep the copied text free of submission IDs and links even if the template changes. */
export function sanitizeSummary(text) {
    return String(text)
        .split('\n')
        .filter(line => !/^\s*id\b\s*[:#]?\s*\d/i.test(line))
        .map(line => line.replace(/https?:\/\/\S+/gi, '').replace(/\/hasil\/\S+/gi, ''))
        .join('\n')
        .trim()
        .slice(0, SUMMARY_MAX);
}

export function mountCopy(scope, button) {
    const root = button.closest?.('[data-result-final], .hl-result-page') ?? button.parentElement;
    const template = root?.querySelector('template[data-summary-source]');
    const status = root?.querySelector('[data-copy-summary-status]');
    if (!template || !status) return;

    const announce = message => { status.textContent = message; };
    const text = () => sanitizeSummary(template.content?.textContent ?? template.textContent ?? '');

    button.hidden = false;
    scope.listen(button, 'click', async () => {
        const value = text();
        if (!value) { announce('Ringkasan tidak tersedia untuk disalin.'); return; }
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard API tidak tersedia.');
            await navigator.clipboard.writeText(value);
            announce('Ringkasan disalin.');
        } catch {
            announce('Tidak dapat menyalin otomatis. Pilih teks ringkasan di halaman ini.');
        }
    });
    scope.own(() => { button.hidden = true; status.textContent = ''; });
}

/**
 * Reveal the optional comment after a rating is chosen.
 * The field stays in the DOM (and in the form) so server-side validation is unchanged.
 */
export function mountCommentReveal(scope, section) {
    const choices = [...(section.querySelectorAll?.('input[name="is_correct"]') ?? [])];
    const comment = section.querySelector?.(`[data-feedback-comment]`);
    if (!comment || !choices.length) return;

    const sync = () => {
        const chosen = choices.some(choice => choice.checked);
        comment.dataset.revealed = chosen ? '' : 'false';
    };
    for (const choice of choices) scope.listen(choice, 'change', sync);
    sync();
    scope.own(() => { delete comment.dataset.revealed; });
}
