export function urlError(value, options) {
    let url;
    try { url = new URL(value); } catch { return 'Masukkan URL lengkap dengan HTTP atau HTTPS.'; }
    if (!['http:', 'https:'].includes(url.protocol)) return 'Gunakan URL HTTP atau HTTPS.';
    if (options) {
        const host = url.hostname.toLowerCase();
        if (options.blockedHosts.some(blocked => host === blocked.toLowerCase() || host.endsWith('.' + blocked.toLowerCase()))) {
            return 'Platform video tidak didukung. Gunakan URL langsung ke file audio/video.';
        }
        const extension = url.pathname.split('.').pop().toLowerCase();
        if (!options.extensions.includes(extension)) return 'URL harus langsung mengarah ke file audio/video dengan format yang didukung.';
    }
    return '';
}

export function fileError(file, kind, options) {
    if (!file.size) return 'File kosong. Pilih file lain.';
    if (file.size > options.maxBytes) return `Ukuran file melebihi ${options.maxSizeLabel}. Pilih file yang lebih kecil.`;
    const reportedMime = file.type.split(';')[0].trim().toLowerCase();
    const mime = reportedMime === 'application/octet-stream' ? '' : reportedMime;
    if (kind === 'video') {
        const extension = file.name.split('.').pop().toLowerCase();
        if (!options.extensions.includes(extension)) return 'Format file video tidak didukung. Pilih format yang tercantum.';
        if (mime && !options.uploadTypes[extension].includes(mime)) return 'Extension dan MIME media tidak kompatibel. Pilih file lain.';
    } else if (mime && !options.mimeTypes.includes(mime)) {
        return 'Format gambar tidak didukung. Pilih format yang tercantum.';
    }
    return '';
}

/**
 * Single-form tab sync (T2.5): exactly one panel stays enabled, so exactly one
 * input_type/raw_input/source_url/media_file can be submitted. The same rule is
 * already enforced on the server for the no-JS render.
 */
function mountSelector(root, scope) {
    const form = root.querySelector('[data-submission-form]');
    if (!form) return;
    const panels = [...form.querySelectorAll('[data-input-fields]')].map(fields => ({
        fields,
        panel: fields.closest('[role="tabpanel"]'),
    }));
    if (!panels.length) return;
    const apply = () => {
        for (const { fields, panel } of panels) {
            const selected = panel?.id === `input-panel-${form.dataset.activeTab}`;
            if (panel) panel.hidden = !selected;
            fields.disabled = !selected;
        }
    };
    const select = key => {
        form.dataset.activeTab = key;
        apply();
    };
    scope.listen(form, 'tabchange', event => select(event.detail?.key ?? form.dataset.activeTab));
    apply();
}

function mountUrlForms(root, scope) {
    for (const input of root.querySelectorAll('[data-source-url]')) {
        const form = input.closest('form');
        if (!form) continue;
        const error = input.parentElement.querySelector('[data-url-error]');
        const preview = input.parentElement.querySelector('[data-url-preview]');
        const serverInvalid = input.getAttribute('aria-invalid');
        const options = input.dataset.direct === 'true' && input.dataset.directUrlOptions
            ? JSON.parse(input.dataset.directUrlOptions)
            : undefined;
        const validate = () => {
            const message = input.value.length > input.maxLength ? `URL maksimal ${input.maxLength} karakter.` : urlError(input.value, options);
            input.setCustomValidity(message);
            return message;
        };
        const show = () => { error.textContent = validate(); error.hidden = !error.textContent; input.setAttribute('aria-invalid', 'true'); };
        const update = () => {
            let url;
            try { url = new URL(input.value); } catch { /* A preview is optional. */ }
            preview.textContent = url && ['http:', 'https:'].includes(url.protocol) ? `Sumber: ${url.hostname}` : '';
        };
        scope.listen(input, 'input', () => {
            const message = validate();
            if (!error.hidden) { error.textContent = message; error.hidden = !message; }
            input.setAttribute('aria-invalid', message || serverInvalid === 'true' ? 'true' : 'false');
            update();
        });
        scope.listen(input, 'invalid', show);
        // Only the enabled panel may block submission; a hidden panel must never
        // hold the form hostage with its own (inactive) value.
        scope.listen(form, 'submit', event => {
            if (input.disabled) return;
            if (validate()) { event.preventDefault(); input.reportValidity(); }
        });
        scope.listen(form, 'reset', event => scope.timeout(() => {
            if (event.defaultPrevented) return;
            input.setCustomValidity(''); error.hidden = true; update();
            input.setAttribute('aria-invalid', serverInvalid);
        }, 0));
        scope.own(() => {
            input.defaultValue = input.value;
            input.setCustomValidity(''); error.hidden = true;
            input.setAttribute('aria-invalid', serverInvalid);
        });
        update();
    }
}

function mountUploads(root, scope) {
    for (const field of root.querySelectorAll('[data-upload-field]')) {
        const input = field.querySelector('[data-file-input]');
        const preview = field.querySelector('[data-file-preview]');
        const error = field.querySelector('[data-file-error]');
        const status = field.querySelector('[data-file-status]');
        const clear = field.querySelector('[data-file-clear]');
        const options = JSON.parse(field.dataset.fileOptions);
        const kind = field.dataset.kind;
        const serverInvalid = input.getAttribute('aria-invalid');
        let release = () => {};
        let source;
        const reset = () => {
            source = undefined;
            release();
            preview.removeAttribute('src');
            if (kind === 'video') preview.load();
            preview.hidden = true; clear.hidden = true; error.hidden = true;
            status.textContent = ''; input.setCustomValidity('');
            input.setAttribute('aria-invalid', serverInvalid);
        };
        const fail = message => {
            input.setCustomValidity(message);
            input.setAttribute('aria-invalid', 'true');
            error.textContent = message; error.hidden = false;
            preview.hidden = true;
        };
        const update = () => {
            reset();
            const file = input.files[0];
            if (!file) return;
            clear.hidden = false;
            status.textContent = `${file.name} — ${(file.size / 1048576).toFixed(2)} MiB`;
            const message = fileError(file, kind, options);
            if (message) { fail(message); return; }
            source = URL.createObjectURL(file);
            const url = source;
            release = scope.own(() => URL.revokeObjectURL(url));
            preview.src = source; preview.hidden = false;
        };
        scope.listen(input, 'change', update);
        scope.listen(clear, 'click', () => { input.value = ''; reset(); input.focus(); });
        scope.listen(input, 'invalid', () => {
            if (!input.files.length) fail('Pilih file terlebih dahulu.');
        });
        scope.listen(preview, kind === 'image' ? 'load' : 'loadedmetadata', () => {
            if (!source || preview.currentSrc !== source) return;
            if (kind === 'image' && (preview.naturalWidth > options.maxWidth || preview.naturalHeight > options.maxHeight)) {
                fail(`Gambar maksimal ${options.maxWidth} × ${options.maxHeight} piksel. Pilih gambar lain.`);
            } else if (kind === 'video' && Number.isFinite(preview.duration) && preview.duration > options.maxDurationSeconds) {
                fail(`Durasi video maksimal ${options.maxDurationSeconds} detik. Pilih video lain.`);
            }
        });
        scope.listen(preview, 'error', () => {
            if (!source) return;
            preview.hidden = true;
            status.textContent += ' — Pratinjau tidak tersedia; server tetap memvalidasi file.';
        });
        scope.listen(input.form, 'submit', event => {
            if (input.disabled) return;
            const file = input.files[0];
            if (file) {
                const message = fileError(file, kind, options);
                if (message) fail(message);
            }
            if (!input.checkValidity()) { event.preventDefault(); input.reportValidity(); }
        });
        scope.listen(input.form, 'reset', event => scope.timeout(() => { if (!event.defaultPrevented) reset(); }, 0));
        scope.own(() => { input.value = ''; reset(); });
    }
}

export function mountMediaInputs(root, scope) {
    mountSelector(root, scope);
    mountUrlForms(root, scope);
    mountUploads(root, scope);
    for (const form of root.querySelectorAll('[data-submission-form]')) {
        const status = form.querySelector('[data-submission-status]');
        scope.listen(form, 'submit', event => scope.timeout(() => {
            if (!event.defaultPrevented) status.textContent = 'Mengirim informasi. Tunggu respons server.';
        }, 0));
        scope.listen(form, 'reset', event => scope.timeout(() => { if (!event.defaultPrevented) status.textContent = ''; }, 0));
        scope.own(() => { status.textContent = ''; form.querySelector('[name="captcha_answer"]').value = ''; });
    }
}
