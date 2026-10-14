import test from 'node:test';
import assert from 'node:assert/strict';
import { urlError, fileError } from '../../resources/js/pages/submission-media.js';

const video = { maxBytes: 100, maxSizeLabel: '100 byte', extensions: ['mp4', 'webm'], uploadTypes: { mp4: ['video/mp4'], webm: ['video/webm'] } };
const image = { maxBytes: 100, maxSizeLabel: '100 byte', mimeTypes: ['image/png', 'image/heic'] };
const remote = { extensions: ['mp4', 'webm', 'mp3'], blockedHosts: ['youtube.com', 'custom.example'] };

test('article URLs require HTTP/HTTPS without contacting the host', () => {
    assert.equal(urlError('https://example.test/news?a=1'), '');
    assert.equal(urlError('http://127.0.0.1/news'), ''); // SSRF remains a server decision.
    assert.match(urlError('javascript:alert(1)'), /HTTP/);
    assert.match(urlError('ftp://example.test/news'), /HTTP/);
    assert.match(urlError('invalid'), /URL lengkap/);
});

test('direct URLs use configured host and remote extension policy', () => {
    assert.match(urlError('https://m.YouTube.com/video.mp4', remote), /Platform/);
    assert.match(urlError('https://sub.custom.example/video.webm', remote), /Platform/);
    assert.equal(urlError('https://notyoutube.com/video.mp4', remote), '');
    assert.equal(urlError('https://example.test/FILE.MP4?signature=fixture#fragment', remote), '');
    assert.equal(urlError('https://example.test/audio.mp3', remote), '');
    assert.match(urlError('https://example.test/player', remote), /langsung/);
    assert.match(urlError('https://example.test/video.exe', remote), /format/);
});

test('file size boundaries and empty files provide recovery', () => {
    assert.equal(fileError({ name: 'file.mp4', type: 'video/mp4', size: 100 }, 'video', video), '');
    assert.match(fileError({ name: 'file.mp4', type: 'video/mp4', size: 101 }, 'video', video), /lebih kecil/);
    assert.match(fileError({ name: 'file.png', type: 'image/png', size: 0 }, 'image', image), /File kosong/);
});

test('upload extension/MIME pairs differ from remote formats; unknown MIME stays server validated', () => {
    assert.match(fileError({ name: 'file.mp3', type: 'audio/mpeg', size: 10 }, 'video', video), /Format/);
    assert.match(fileError({ name: 'file.mp4', type: 'video/webm', size: 10 }, 'video', video), /MIME/);
    assert.equal(fileError({ name: 'FILE.WEBM', type: '', size: 10 }, 'video', video), '');
    assert.equal(fileError({ name: 'FILE.WEBM', type: 'application/octet-stream', size: 10 }, 'video', video), '');
    assert.equal(fileError({ name: 'file.heic', type: 'image/heic', size: 10 }, 'image', image), '');
    assert.match(fileError({ name: 'file.svg', type: 'image/svg+xml', size: 10 }, 'image', image), /Format gambar/);
});
