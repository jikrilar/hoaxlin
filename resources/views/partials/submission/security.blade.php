<input type="text" name="website" class="hl-submission-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true">
<div class="hl-submission-security">
    <x-ui.field :id="'captcha-'.$suffix" :label="'Keamanan: Berapa '.$captchaQuestion.'?'"
        hint="Jawab pertanyaan ini untuk mengirim pemeriksaan."
        :error="($previousInputType ?? 'text') === $type ? $errors->first('captcha_answer') : null">
        <x-forms.input type="number" name="captcha_answer" required inputmode="numeric" autocomplete="off" />
    </x-ui.field>
</div>
