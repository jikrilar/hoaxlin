<!-- Input Type Tabs -->
<div class="input-tabs" role="tablist" aria-label="Jenis input berita" style="margin-bottom:1.75rem;">
    <button id="tab-teks" class="input-tab {{ $activeTab === 'teks' ? 'active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'teks' ? 'true' : 'false' }}" aria-controls="panel-teks" type="button" data-input-tab="teks">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Teks
    </button>
    @auth
    <button id="tab-gambar" class="input-tab {{ $activeTab === 'gambar' ? 'active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'gambar' ? 'true' : 'false' }}" aria-controls="panel-gambar" type="button" data-input-tab="gambar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Gambar
    </button>
    <button id="tab-video" class="input-tab {{ $activeTab === 'video' ? 'active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'video' ? 'true' : 'false' }}" aria-controls="panel-video" type="button" data-input-tab="video">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
        Video
    </button>
    <button id="tab-url" class="input-tab {{ $activeTab === 'url' ? 'active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'url' ? 'true' : 'false' }}" aria-controls="panel-url" type="button" data-input-tab="url">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
        Tautan URL
    </button>
    @endauth
</div>
