@if($bag->any())
    <x-ui.alert tone="danger" :title="$title" role="alert" :id="$id" tabindex="-1" :autofocus="$focus" class="hl-profile-errors">
        <ul>
            @foreach($bag->messages() as $field => $messages)
                @foreach($messages as $message)
                    <li>
                        @if(isset($targets[$field]))
                            <a href="#{{ $targets[$field] }}">{{ $message }}</a>
                        @else
                            {{ $message }}
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </x-ui.alert>
@endif
