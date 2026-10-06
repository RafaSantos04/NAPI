{{-- Labelled input with its error message tied to it (aria-describedby).
     Params: name, label, type?, value?, autocomplete?, hint?. Password
     fields are never given a value. --}}
@php
    $type ??= 'text';
    $describedBy = collect([
        isset($hint) ? "{$name}-hint" : null,
        $errors->has($name) ? "{$name}-error" : null,
    ])->filter()->join(' ');
@endphp

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $value ?? '' }}" @endif
        autocomplete="{{ $autocomplete ?? 'off' }}"
        required
        @error($name) aria-invalid="true" @enderror
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    >
    @isset($hint)
        <p id="{{ $name }}-hint" class="field-hint">{{ $hint }}</p>
    @endisset
    @error($name)
        <p id="{{ $name }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
