{{-- Actions/feedback migration belongs to IP-016. Final result is guarded by the parent view. --}}
        <!-- Feedback Section -->
        @if($result)
        <div class="glass-card" style="padding:2rem; margin-bottom:1.5rem;" role="region" aria-label="Umpan balik">
            <h2 style="font-size:1.0625rem; font-weight:var(--ds-font-weight-semibold); margin-bottom:0.5rem;">Apakah hasil ini akurat?</h2>
            <p style="color:var(--color-text-muted); font-size:0.875rem; margin-bottom:1.25rem;">
                Umpan balikmu membantu evaluasi dan pengembangan model BERT di masa depan.
            </p>

            @auth
            @if($feedback ?? null)
                <div style="padding:1.25rem; background:var(--ds-success-bg); border:1px solid var(--ds-success-fg); border-radius:0.875rem;">
                    <p style="color:var(--ds-success-fg); font-weight:600; margin-bottom:0.375rem;">
                        {{ $feedback->is_correct ? '✅ Prediksi ditandai benar' : '❌ Prediksi ditandai tidak benar' }}
                    </p>
                    @if($feedback->comment)
                        <p style="color:var(--color-text-secondary); font-size:0.875rem; line-height:1.6; margin-bottom:0.5rem;">{{ $feedback->comment }}</p>
                    @endif
                    <p style="color:var(--color-text-muted); font-size:0.75rem;">Dikirim {{ $feedback->created_at->format('d M Y, H:i') }}</p>
                </div>
            @elseif(auth()->id() === $submission->user_id)
            <form action="{{ route('feedback') }}" method="POST" id="feedback-form">
                @csrf
                <input type="hidden" name="submission_id" value="{{ $submission->id ?? '' }}">

                <div style="display:flex; gap:0.75rem; margin-bottom:1.25rem;" role="group" aria-label="Pilih umpan balik">
                    <button type="button" id="fb-correct" onclick="selectFeedback(true)"
                        class="btn-secondary" style="flex:1; justify-content:center;"
                        aria-pressed="false">
                        ✅ Ya, Sudah Benar
                    </button>
                    <button type="button" id="fb-wrong" onclick="selectFeedback(false)"
                        class="btn-secondary" style="flex:1; justify-content:center;"
                        aria-pressed="false">
                        ❌ Tidak Akurat
                    </button>
                </div>

                <input type="hidden" name="is_correct" id="feedback-is-correct" value="">

                @error('is_correct')
                    <p class="auth-error" role="alert" style="margin-bottom:1rem;">{{ $message }}</p>
                @enderror

                <div id="feedback-comment-area" style="display:none;">
                    <label for="feedback-comment" style="display:block; color:var(--color-text-secondary); font-size:0.875rem; margin-bottom:0.5rem;">Komentar (opsional)</label>
                    <textarea id="feedback-comment" name="comment" class="form-textarea" style="height:100px;" maxlength="1000" placeholder="Tambahkan komentar tentang hasil prediksi...">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="btn-primary" style="margin-top:1rem;">
                        <span>Kirim Umpan Balik</span>
                    </button>
                </div>
            </form>
            @else
                <div class="info-box">Umpan balik hanya dapat diberikan oleh pemilik submission.</div>
            @endif

            @else
            <div class="info-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>
                    <a href="{{ route('login') }}" style="color:var(--color-primary-light); text-decoration:none; font-weight:500;">Masuk ke akun</a>
                    untuk memberikan umpan balik dan membantu evaluasi model di masa depan.
                </span>
            </div>
            @endauth
        </div>
        @endif

        <!-- Action Buttons -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap:1rem;">
            <a href="{{ url('/') }}#cek-berita" class="btn-secondary" style="justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Cek Berita Lain
            </a>
            @if($result)
                <a href="{{ route('hasil.pdf', $submission->id) }}" class="btn-ghost" style="border:1px solid var(--color-border-light); justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Unduh PDF
                </a>
            @else
                <button onclick="window.print()" class="btn-ghost" style="border:1px solid var(--color-border-light); justify-content:center;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Cetak / Simpan PDF
                </button>
            @endif
        </div>
        @auth
            <div style="text-align:center; margin-top:1rem;">
                <a href="{{ route('riwayat.csv') }}" style="color:var(--color-text-muted); font-size:0.8125rem; text-decoration:underline;">Unduh Riwayat CSV</a>
            </div>
        @endauth
