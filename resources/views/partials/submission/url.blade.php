{{-- URL field shared by article links and direct media URLs; the active panel owns submission. --}}
@php($direct = is_bool($direct ?? null) ? $direct : false)
<x-ui.field :id="$inputId" :label="$label" :hint="$hint" :error="$errors->first('source_url')">
    <input id="{{ $inputId }}" type="url" name="source_url" value="{{ $previous }}"
        class="hl-input" required pattern="[Hh][Tt][Tt][Pp][Ss]?://.+"
        maxlength="{{ $submissionOptions[$direct ? 'videoUrl' : 'url']['maxLength'] }}"
        placeholder="https://contoh.id/informasi" aria-describedby="{{ $inputId }}-client-error" data-source-url
        @if($direct) data-direct-url-options="{{ json_encode($submissionOptions['videoUrl']) }}" @endif
        data-direct="{{ $direct ? 'true' : 'false' }}">
    <p id="{{ $inputId }}-client-error" class="hl-field__error" data-url-error role="alert" hidden></p>
    <p class="hl-field__hint" data-url-preview></p>
</x-ui.field>
