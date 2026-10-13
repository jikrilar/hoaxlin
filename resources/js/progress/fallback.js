import { createScope } from '../core/scope.js';

export const isTerminal = status => status === 'completed' || status === 'failed';

export function readStatus(data, id) {
    if (!data || String(data.id) !== String(id) || typeof data.status !== 'string'
        || typeof data.is_completed !== 'boolean' || typeof data.is_failed !== 'boolean'
        || data.is_completed !== (data.status === 'completed') || data.is_failed !== (data.status === 'failed')
        || !Number.isInteger(data.progress) || data.progress < 0 || data.progress > 100
        || !(data.processing_stage === null || typeof data.processing_stage === 'string')
        || typeof data.stage_label !== 'string') {
        throw new Error('Invalid status response.');
    }
    // Classification, explanation and failure payloads are never rendered here.
    return { status: data.status, stage: data.processing_stage, stageLabel: data.stage_label, progress: data.progress };
}

export function retryAfter(value, now = Date.now()) {
    if (!value) return 0;
    const seconds = Number(value);
    const delay = Number.isFinite(seconds) ? seconds * 1000 : Date.parse(value) - now;
    return Number.isFinite(delay) ? Math.max(0, delay) : 0;
}

export function responseError(response, now = Date.now()) {
    const status = response.status;
    const access = [401, 403, 404, 410, 419].includes(status)
        || response.type === 'opaqueredirect' || response.redirected || (status >= 300 && status < 400);
    return {
        kind: access ? 'access' : (status === 429 ? 'rate' : 'transport'),
        fatal: access || (status >= 400 && status < 500 && status !== 429),
        delay: status === 429 ? retryAfter(response.headers.get('Retry-After'), now) : 0,
    };
}

/** Sequential requests; a paused/retired request cannot update or restart a root. */
export function createFallback({ url, origin, id, fetch: fetchStatus = globalThis.fetch,
    clock = globalThis, active = () => true, beforeRequest = () => true,
    onStatus, onError, interval = 2000, requestTimeout = 10000 }) {
    const endpoint = new URL(url, origin);
    if (endpoint.origin !== origin || !['http:', 'https:'].includes(endpoint.protocol) || endpoint.username || endpoint.password) {
        throw new TypeError('Status URL must be same-origin.');
    }
    const scope = createScope({ clock });
    let enabled = false;
    let timer;
    let request;
    let delay = interval;
    const clearTimer = () => { timer?.(); timer = undefined; };
    const eligible = () => enabled && !scope.signal.aborted && active();
    function schedule() {
        if (!eligible() || request || timer) return;
        timer = scope.timeout(() => { timer = undefined; run(); }, delay);
    }
    async function run() {
        if (!eligible() || request || !beforeRequest() || !eligible()) return;
        const controller = new AbortController();
        request = controller;
        let timedOut = false;
        let rejectAbort;
        const aborted = new Promise((_, reject) => { rejectAbort = reject; });
        const abort = () => rejectAbort(new Error('Status request aborted.'));
        controller.signal.addEventListener('abort', abort, { once: true });
        const deadline = scope.timeout(() => { timedOut = true; controller.abort(); }, requestTimeout);
        delay = interval;
        try {
            const operation = (async () => {
                const response = await fetchStatus(endpoint.href, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin',
                    redirect: 'manual', cache: 'no-store', signal: controller.signal,
                });
                if (controller.signal.aborted) throw new Error('Status request aborted.');
                if (!response.ok || response.type === 'opaqueredirect' || response.redirected) {
                    throw responseError(response, clock.Date?.now() ?? Date.now());
                }
                if (!/\bapplication\/(?:[\w.-]+\+)?json\b/i.test(response.headers.get('Content-Type') ?? '')) {
                    throw new Error('Status response is not JSON.');
                }
                return readStatus(await response.json(), id);
            })();
            const data = await Promise.race([operation, aborted]);
            if (eligible() && !controller.signal.aborted) onStatus(data);
        } catch (error) {
            if (eligible() && (!controller.signal.aborted || timedOut)) {
                const next = onError(error?.kind ? error : { kind: 'transport', fatal: false, delay: 0 });
                if (next === null) enabled = false;
                else delay = next ?? interval;
            }
        } finally {
            deadline();
            controller.signal.removeEventListener('abort', abort);
            if (request === controller) request = undefined;
            schedule();
        }
    }
    const pause = () => { enabled = false; clearTimer(); request?.abort(); };
    return {
        resume(nextDelay = interval) { if (scope.signal.aborted) return; enabled = true; delay = nextDelay; schedule(); },
        pause,
        dispose() { pause(); scope.dispose(); },
    };
}
