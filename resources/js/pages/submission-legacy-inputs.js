/** Transitional adapters for existing media panels; their UI migration is IP-012. */
export function mountLegacyInputs(root, scope) {
    const find = id => root.querySelector(`#${id}`);
    const tabs = [...root.querySelectorAll('[data-input-tab]')];
    const select = tab => {
        for (const button of tabs) {
            const selected = button === tab;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
            find(button.getAttribute('aria-controls')).classList.toggle('active', selected);
        }
    };
    for (const tab of tabs) {
        scope.listen(tab, 'click', () => select(tab));
        scope.listen(tab, 'keydown', event => {
            const index = tabs.indexOf(tab);
            const target = { ArrowRight: (index + 1) % tabs.length, ArrowLeft: (index - 1 + tabs.length) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key];
            if (target === undefined) return;
            event.preventDefault();
            select(tabs[target]);
            tabs[target].focus();
        });
        tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
    }
    const videoForm = find('form-video');
    if (!videoForm) return;
    const videoUrl = find('video-url-input');
    const videoFile = find('video-file-input');
    const blockedHosts = JSON.parse(videoForm.dataset.blockedVideoHosts);
    const validateUrl = () => {
        let blocked = false;
        try {
            const host = new URL(videoUrl.value.trim()).hostname.toLowerCase();
            blocked = blockedHosts.some(value => host === value || host.endsWith('.' + value));
        } catch { /* Native URL validation and the server remain authoritative. */ }
        find('video-url-platform-error').hidden = !blocked;
        videoUrl.setAttribute('aria-invalid', String(blocked));
        return !blocked;
    };
    scope.listen(videoUrl, 'input', validateUrl);
    const previews = new Map();
    for (const zone of root.querySelectorAll('[data-file-drop]')) {
        const type = zone.dataset.fileDrop;
        const input = zone.querySelector('input[type="file"]');
        const label = find(type === 'gambar' ? 'gambar-preview-label' : 'video-preview-label');
        const original = [...label.childNodes];
        const image = type === 'gambar' ? find('gambar-preview-img') : null;
        let releasePreview = () => {};
        const reset = () => {
            releasePreview();
            label.replaceChildren(...original);
            if (image) { image.removeAttribute('src'); image.style.display = 'none'; }
        };
        const preview = file => {
            reset();
            if (!file) return;
            if (type === 'video' && file.size > Number(videoForm.dataset.videoMaxBytes)) {
                input.value = '';
                input.setAttribute('aria-invalid', 'true');
                find('video-size-error').hidden = false;
                return;
            }
            if (type === 'video') { input.removeAttribute('aria-invalid'); find('video-size-error').hidden = true; }
            // A filename is text, never interpolated HTML.
            label.textContent = `${file.name} — ${(file.size / 1024 / 1024).toFixed(2)} MiB`;
            if (image) {
                const url = URL.createObjectURL(file);
                releasePreview = scope.own(() => URL.revokeObjectURL(url));
                image.src = url;
                image.style.display = 'block';
            }
        };
        previews.set(type, preview);
        scope.listen(input, 'change', () => preview(input.files[0]));
        scope.listen(zone, 'dragover', event => { event.preventDefault(); zone.classList.add('dragover'); });
        scope.listen(zone, 'dragleave', () => zone.classList.remove('dragover'));
        scope.listen(zone, 'drop', event => {
            event.preventDefault();
            zone.classList.remove('dragover');
            const file = event.dataTransfer.files[0];
            if (!file) return;
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            preview(file);
        });
        scope.own(() => { input.value = ''; reset(); zone.classList.remove('dragover'); });
    }
    for (const button of root.querySelectorAll('[data-video-mode]')) {
        scope.listen(button, 'click', () => {
            const upload = button.dataset.videoMode === 'upload';
            find('video-upload-panel').style.display = upload ? 'block' : 'none';
            find('video-url-panel').style.display = upload ? 'none' : 'block';
            for (const candidate of root.querySelectorAll('[data-video-mode]')) {
                const selected = candidate === button;
                candidate.setAttribute('aria-pressed', String(selected));
                candidate.style.background = selected ? 'var(--ds-bg-selected)' : 'transparent';
                candidate.style.color = selected ? 'var(--ds-link)' : 'var(--ds-text-muted)';
                candidate.style.borderColor = selected ? 'var(--ds-border-control)' : 'var(--ds-border-subtle)';
            }
            find('video-input-type').value = upload ? 'video' : 'video_url';
            videoFile.required = upload;
            videoUrl.required = !upload;
            if (!upload) find('video-size-error').hidden = true;
        });
    }
    scope.listen(videoForm, 'submit', event => {
        if (find('video-input-type').value === 'video') {
            const file = videoFile.files[0];
            if (file && file.size > Number(videoForm.dataset.videoMaxBytes)) { event.preventDefault(); previews.get('video')(file); }
        } else if (!validateUrl()) { event.preventDefault(); videoUrl.focus(); }
    });
    const urlInput = find('url-input');
    const updateUrl = () => {
        const value = urlInput.value.trim();
        let url;
        try { url = new URL(value); } catch { /* Invalid URLs have no preview. */ }
        const visible = url && ['http:', 'https:'].includes(url.protocol);
        find('url-preview').style.display = visible ? 'block' : 'none';
        find('url-domain').textContent = visible ? url.hostname : '';
        find('url-full').textContent = visible ? value : '';
    };
    scope.listen(urlInput, 'input', updateUrl);
    updateUrl();
}
