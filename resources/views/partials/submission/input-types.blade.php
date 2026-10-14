<nav class="hl-input-selector" aria-label="Jenis input" data-input-selector>
    @foreach(['teks' => 'Teks', 'url' => 'Tautan artikel', 'gambar' => 'Gambar', 'video' => 'Video'] as $key => $label)
        <a id="tab-{{ $key }}" class="hl-input-option" href="#panel-{{ $key }}" data-input-tab="{{ $key }}">{{ $label }}</a>
    @endforeach
</nav>
