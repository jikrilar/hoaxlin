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

function mountSelector(root, scope) {
    const container = root.querySelector('[data-submission-selector]');
    if (!container) return;
    const panels = [...container.querySelectorAll('[data-input-panel]')];
    const videos = [...container.querySelectorAll('[data-video-panel]')];
    const tabs = [...container.querySelectorAll('[data-input-tab]')];
    const modes = [...container.querySelectorAll('[data-video-mode]')];
    const apply = () => {
        for (const panel of panels) {
            const selected = panel.dataset.inputPanel === container.dataset.activeInput;
            panel.hidden = !selected;
            for (const fields of panel.querySelectorAll('[data-submission-fields]')) fields.disabled = !selected;
        }
        for (const panel of videos) {
            const selected = container.dataset.activeInput === 'video' && panel.dataset.videoPanel === container.dataset.activeVideo;
            panel.hidden = panel.dataset.videoPanel !== container.dataset.activeVideo;
            panel.querySelector('[data-submission-fields]').disabled = !selected;
        }
        for (const [links, value, key] of [[tabs, container.dataset.activeInput, 'inputTab'], [modes, container.dataset.activeVideo, 'videoMode']]) {
            for (const link of links) {
                const selected = link.dataset[key] === value;
                link.setAttribute('aria-selected', String(selected));
                link.tabIndex = selected ? 0 : -1;
            }
        }
    };
    for (const [links, key, stateKey] of [[tabs, 'inputTab', 'activeInput'], [modes, 'videoMode', 'activeVideo']]) {
        if (!links.length) continue;
        const nav = links[0].parentElement;
        nav.setAttribute('role', 'tablist');
        scope.own(() => nav.removeAttribute('role'));
        for (const link of links) {
            const panel = container.querySelector(link.getAttribute('href'));
            link.setAttribute('role', 'tab');
            link.setAttribute('aria-controls', panel.id);
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', link.id);
            const select = () => { container.dataset[stateKey] = link.dataset[key]; apply(); };
            scope.listen(link, 'click', event => { event.preventDefault(); select(); });
            scope.listen(link, 'keydown', event => {
                if (event.key === ' ') { event.preventDefault(); select(); return; }
                const index = links.indexOf(link);
                const target = { ArrowRight: (index + 1) % links.length, ArrowLeft: (index - 1 + links.length) % links.length, Home: 0, End: links.length - 1 }[event.key];
                if (target === undefined) return;
                event.preventDefault();
                container.dataset[stateKey] = links[target].dataset[key];
                apply();
                links[target].focus();
            });
            scope.own(() => {
                for (const attribute of ['role', 'aria-controls', 'aria-selected', 'tabindex']) link.removeAttribute(attribute);
                panel.removeAttribute('role');
                panel.removeAttribute('aria-labelledby');
            });
        }
    }
    container.dataset.enhanced = '';
    apply();
    scope.own(() => {
        delete container.dataset.enhanced;
        for (const panel of [...panels, ...videos]) panel.hidden = false;
        for (const fields of container.querySelectorAll('[data-submission-fields]')) fields.disabled = false;
    });
}

function mountUrlForms(root, scope) {
    for (const form of root.querySelectorAll('[data-url-submission]')) {
        const input = form.querySelector('[data-source-url]');
        const error = form.querySelector('[data-url-error]');
        const preview = form.querySelector('[data-url-preview]');
        const serverInvalid = input.getAttribute('aria-invalid');
        const options = form.dataset.directUrlOptions ? JSON.parse(form.dataset.directUrlOptions) : undefined;
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
        scope.listen(form, 'submit', event => {
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
    for (const form of root.querySelectorAll('[data-url-submission], [data-upload-submission]')) {
        const status = form.querySelector('[data-submission-status]');
        scope.listen(form, 'submit', event => scope.timeout(() => {
            if (!event.defaultPrevented) status.textContent = 'Mengirim informasi. Tunggu respons server.';
        }, 0));
        scope.listen(form, 'reset', event => scope.timeout(() => { if (!event.defaultPrevented) status.textContent = ''; }, 0));
        scope.own(() => { status.textContent = ''; form.querySelector('[name="captcha_answer"]').value = ''; });
    }
}
