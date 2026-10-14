import { createScope, defineModule } from '../core/scope.js';
import { createFallback, isTerminal, responseError } from '../progress/fallback.js';
import { observeLivewire } from '../progress/livewire.js';

const transitions = new WeakMap();
const setText = (element, value) => { if (element && element.textContent !== value) element.textContent = value; };

export function createProgress(root, { clock = root.ownerDocument.defaultView,
    fetch: fetchStatus = (...args) => root.ownerDocument.defaultView.fetch(...args),
    reload = () => root.ownerDocument.defaultView.location.reload() } = {}) {
    const doc = root.ownerDocument;
    const view = doc.defaultView;
    const scope = createScope({ clock });
    const id = root.dataset.submissionId;
    const initialTerminal = isTerminal(root.dataset.initialStatus);
    const stages = new Set(JSON.parse(root.dataset.progressStages));
    const now = () => clock.Date?.now() ?? Date.now();
    let fallback;
    let native;
    let owner;
    let paused = false;
    let halted = false;
    let failures = 0;
    let nextAllowedAt = 0;
    const active = () => !scope.signal.aborted && root.isConnected && root.dataset.submissionId === id;
    const notice = message => {
        const element = root.querySelector('[data-progress-transport]');
        if (!element || !active()) return;
        setText(element, message);
        element.hidden = !message;
    };
    const dispose = () => scope.dispose();
    function finish() {
        if (!active()) return;
        const alreadyTransitioned = transitions.get(root) === id;
        transitions.set(root, id);
        dispose(); // Abort/timers/hooks stop before requesting the authorized HTML page.
        if (!initialTerminal && !alreadyTransitioned && root.isConnected && root.dataset.submissionId === id) reload();
    }
    function failure(error) {
        if (!active()) return null;
        failures++;
        const delay = Math.max(2000 * 2 ** failures, error.delay ?? 0);
        halted = error.fatal || failures >= 3 || delay > 2147483647;
        nextAllowedAt = now() + delay;
        notice(error.kind === 'access'
            ? 'Sesi atau akses tidak lagi tersedia. Muat ulang halaman untuk memeriksa akses.'
            : (error.kind === 'rate'
                ? 'Pembaruan status dibatasi sementara. Gunakan “Muat ulang status” jika diperlukan.'
                : 'Belum dapat memperbarui status. Status terakhir tetap ditampilkan. Gunakan “Muat ulang status” jika diperlukan.'));
        if (halted) fallback?.pause();
        return halted ? null : delay;
    }
    function success() { if (!active() || halted) return; failures = 0; nextAllowedAt = 0; notice(''); }
    function render(data) {
        if (!active()) return;
        if (isTerminal(data.status)) { finish(); return; }
        success();
        const element = root.querySelector('[data-progress-state]');
        if (!element) return;
        const knownStatus = ['pending', 'processing'].includes(data.status);
        const knownStage = stages.has(data.stage);
        const stageLabel = knownStage ? data.stageLabel : 'Belum tersedia';
        element.dataset.progressState = knownStatus ? data.status : 'unknown';
        setText(element.querySelector('h1'), data.status === 'pending' ? 'Menunggu pemrosesan'
            : (data.status === 'processing' ? 'Sedang Menganalisis' : 'Status pemrosesan belum tersedia'));
        setText(element.querySelector('[data-progress-status]'), data.status === 'pending' ? 'Menunggu'
            : (data.status === 'processing' ? 'Diproses' : 'Belum tersedia'));
        setText(element.querySelector('[data-stage-label]'), stageLabel);
        setText(element.querySelector('.hl-submission-progress__description'), knownStatus
            ? 'Input sedang menunggu atau menjalani analisis. Hasil final tersedia setelah status pemrosesan selesai.'
            : 'Status terbaru belum dapat dijelaskan. Muat ulang halaman untuk memeriksa pembaruan.');
        setText(element.querySelector('[data-progress-percent]'), `${data.progress}%`);
        const meter = element.querySelector('.hl-submission-progress__meter');
        if (meter) meter.hidden = !knownStatus;
        const bar = element.querySelector('[role="progressbar"]');
        if (bar) {
            bar.setAttribute('aria-valuenow', data.progress);
            bar.setAttribute('aria-valuetext', `${data.progress} persen; ${stageLabel}; status ${knownStatus ? data.status : 'belum tersedia'}`);
            bar.firstElementChild.style.width = `${data.progress}%`;
        }
        const unavailable = element.querySelector('[data-progress-stage-unavailable]');
        if (unavailable) unavailable.hidden = knownStage;
        const notFinal = element.querySelector('[data-progress-not-final-done]');
        if (notFinal) notFinal.hidden = data.stage !== 'done';
    }
    function sync() {
        if (!active()) { dispose(); return; }
        if (isTerminal(root.querySelector('[data-progress-state]')?.dataset.progressState)) { finish(); return; }
        if (halted) return;
        native.probe();
        if (doc.hidden || view.navigator.onLine === false) {
            paused = true;
            fallback.pause();
            if (view.navigator.onLine === false) notice('Tidak ada koneksi. Status belum terbarui; pembaruan dilanjutkan setelah koneksi kembali.');
            return;
        }
        if (owner === 'livewire') { paused = false; return; }
        if (!owner || paused) {
            const resuming = paused;
            owner = 'fallback';
            paused = false;
            fallback.resume(resuming ? Math.max(0, nextAllowedAt - now()) : 2000);
        }
    }
    if (initialTerminal || transitions.get(root) === id) { dispose(); return dispose; }
    try {
        fallback = createFallback({ url: root.dataset.statusUrl, origin: view.location.origin, id, clock, fetch: fetchStatus,
            active: () => active() && !halted && !doc.hidden && view.navigator.onLine !== false && owner !== 'livewire',
            beforeRequest: () => { sync(); return owner === 'fallback'; }, onStatus: render, onError: failure });
    } catch {
        failure({ kind: 'access', fatal: true });
        return dispose;
    }
    scope.own(() => fallback.dispose());
    native = observeLivewire(root, scope, {
        onOwner: () => { fallback.pause(); owner = 'livewire'; if (!halted) success(); },
        onChange: sync, now,
        blocked: time => halted || !active() || time < nextAllowedAt,
        onError: response => failure(response ? responseError(response, now()) : { kind: 'transport', fatal: false }),
        onSuccess: success,
    });
    scope.listen(doc, 'visibilitychange', sync);
    scope.listen(view, 'online', sync);
    scope.listen(view, 'offline', sync);
    const observer = new view.MutationObserver(sync);
    observer.observe(doc.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-progress-state'] });
    scope.own(() => observer.disconnect());
    sync();
    return dispose;
}

export const mount = defineModule((body, scope) => {
    for (const root of body.querySelectorAll('[data-page="result"] [data-progress-root]')) scope.own(createProgress(root));
});
