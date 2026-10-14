@if ($errors->any())
    @php
        $errorTargets = [
            'raw_input' => 'teks-input',
            'captcha_answer' => 'captcha-'.($videoUrlActive ? 'video-url' : $activeTab),
            'media_file' => $activeTab === 'gambar' ? 'gambar-input' : 'video-file-input',
            'source_url' => $videoUrlActive ? 'video-url-input' : 'url-input',
        ];
    @endphp
    <div id="submission-errors" role="alert" tabindex="-1" autofocus class="hl-submission-errors" data-submission-errors>
        <h3>Periksa kembali input kamu:</h3>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $error)
                    <li>
                        @if(isset($errorTargets[$field]) && (auth()->check() || in_array($field, ['raw_input', 'captcha_answer'], true)))
                            <a href="#{{ $errorTargets[$field] }}">{{ $error }}</a>
                        @else
                            {{ $error }}
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
        @if (in_array($previousInputType, ['image', 'video'], true))
            <p>File unggahan perlu dipilih ulang.</p>
        @endif
    </div>
@endif
