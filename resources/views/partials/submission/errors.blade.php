@if ($errors->any())
    @php
        // Only the panel that failed is reported; that panel is also the one
        // HomeController restores, so every link below targets visible markup.
        $errorTargets = [
            'raw_input' => 'teks-input',
            'captcha_answer' => 'captcha-answer',
            'media_file' => $previousInputType === 'video' ? 'video-file-input' : 'gambar-input',
            'source_url' => $previousInputType === 'video_url' ? 'video-url-input' : 'url-input',
        ];
    @endphp
    <div id="submission-errors" role="alert" tabindex="-1" autofocus class="hl-submission-errors" data-submission-errors>
        <h3>Periksa kembali isian Anda:</h3>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $error)
                    <li>
                        @if(isset($errorTargets[$field]))
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
