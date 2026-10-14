<x-ui.alert tone="danger" title="Periksa kembali formulir" role="alert" id="auth-errors" class="hl-auth-errors"
    :tabindex="$targets ? '-1' : null" :autofocus="(bool) $targets">
    <ul>
        @foreach($errors->messages() as $field => $messages)
            @foreach($messages as $error)
                <li>
                    @if(isset($targets[$field]))
                        <a href="#{{ $targets[$field] }}">{{ $error }}</a>
                    @else
                        {{ $error }}
                    @endif
                </li>
            @endforeach
        @endforeach
    </ul>
</x-ui.alert>
