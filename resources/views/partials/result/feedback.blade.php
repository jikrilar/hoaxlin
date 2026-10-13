@if($result)
    @php
        $ownFeedback = $ownsResult && ($feedback ?? null)
            && $feedback->user_id === $resultViewer->getKey()
            && $feedback->submission_id === $submission->getKey() ? $feedback : null;
        $feedbackErrors = \Illuminate\Support\Arr::only($errors->messages(), ['submission_id', 'is_correct', 'comment']);
    @endphp
    <section class="hl-result-page__content hl-result-feedback" aria-labelledby="feedback-heading">
        <h2 id="feedback-heading">Umpan balik tentang prediksi</h2>
        <p class="hl-result__hint">Sampaikan penilaian Anda atas prediksi model. Umpan balik bukan voting kebenaran informasi dan tidak mengubah hasil analisis ini.</p>
        @if(! $ownsResult)
            <p>Umpan balik hanya tersedia untuk hasil yang dikirim melalui akun Anda.</p>
        @elseif(! $verifiedViewer)
            <p>Verifikasi email akun Anda untuk memberikan umpan balik.</p>
            <a class="hl-button" data-variant="secondary" href="{{ route('verification.notice') }}">Verifikasi email</a>
        @else
            @if($feedbackErrors)
                <x-ui.alert tone="danger" title="Pengiriman umpan balik tidak berhasil" id="feedback-errors" role="alert" tabindex="-1" autofocus class="hl-result-feedback__errors">
                    <ul>
                        @foreach($feedbackErrors as $field => $messages)
                            @foreach($messages as $message)
                                <li>
                                    @if(! $ownFeedback && in_array($field, ['is_correct', 'comment'], true))
                                        <a href="#{{ $field === 'is_correct' ? 'feedback-correct' : 'feedback-comment' }}">{{ $message }}</a>
                                    @else
                                        {{ $message }}
                                    @endif
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif
            @if($ownFeedback)
                <x-ui.alert tone="success" title="Umpan balik sudah dikirim" data-feedback-sent>
                    <p>Penilaian Anda: {{ $ownFeedback->is_correct ? 'Sesuai' : 'Tidak sesuai' }}.</p>
                    @if(filled($ownFeedback->comment))
                        <p class="hl-result__text">{{ $ownFeedback->comment }}</p>
                    @endif
                    @if($ownFeedback->created_at)
                        <p class="hl-result__hint">Dikirim <time datetime="{{ $ownFeedback->created_at->toIso8601String() }}">{{ $ownFeedback->created_at->format('d M Y, H:i T') }}</time></p>
                    @endif
                    <p>Umpan balik untuk hasil ini hanya dapat dikirim sekali.</p>
                </x-ui.alert>
            @else
                @php
                    $previousSubmission = old('submission_id');
                    $sameFeedback = is_scalar($previousSubmission) && (string) $previousSubmission === (string) $submission->id;
                    $previousChoice = $sameFeedback ? old('is_correct') : null;
                    $previousComment = $sameFeedback && is_string(old('comment')) ? old('comment') : '';
                @endphp
                <form action="{{ route('feedback') }}" method="POST" id="feedback-form" data-submit-label="Mengirim...">
                    @csrf
                    <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                    <fieldset class="hl-result-feedback__choice" aria-describedby="feedback-choice-hint{{ $errors->has('is_correct') ? ' feedback-choice-error' : '' }}">
                        <legend>Apakah prediksi model sesuai menurut Anda?</legend>
                        <p id="feedback-choice-hint" class="hl-field__hint">Pilih satu penilaian untuk prediksi ini.</p>
                        <div class="hl-result-feedback__options">
                            <label for="feedback-correct"><input id="feedback-correct" type="radio" name="is_correct" value="1" required @checked(in_array($previousChoice, [true, 1, '1'], true)) aria-invalid="{{ $errors->has('is_correct') ? 'true' : 'false' }}">Sesuai</label>
                            <label for="feedback-incorrect"><input id="feedback-incorrect" type="radio" name="is_correct" value="0" required @checked(in_array($previousChoice, [false, 0, '0'], true)) aria-invalid="{{ $errors->has('is_correct') ? 'true' : 'false' }}">Tidak sesuai</label>
                        </div>
                        @error('is_correct')
                            <p id="feedback-choice-error" class="hl-field__error">{{ $message }}</p>
                        @enderror
                    </fieldset>
                    <x-ui.field id="feedback-comment" label="Komentar (opsional)" hint="Maksimal 1.000 karakter. Anda dapat menjelaskan alasan penilaian Anda." :error="$errors->first('comment')">
                        <textarea id="feedback-comment" name="comment" class="hl-input hl-result-feedback__comment" maxlength="1000"
                            aria-invalid="{{ $errors->has('comment') ? 'true' : 'false' }}"
                            aria-describedby="feedback-comment-hint{{ $errors->has('comment') ? ' feedback-comment-error' : '' }}">{{ $previousComment }}</textarea>
                    </x-ui.field>
                    <x-ui.button type="submit" id="feedback-submit">Kirim umpan balik</x-ui.button>
                </form>
            @endif
        @endif
    </section>
@endif
