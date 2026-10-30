@extends('layouts.admin-shell')

@section('title', 'Novo token')

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.tokens.index') }}">← Meus tokens</a>
            <h1>Novo token</h1>
        </div>
    </div>

    <form class="form" method="POST" action="{{ route('admin.tokens.store') }}" novalidate>
        @csrf

        @include('admin.partials.field', ['name' => 'name', 'label' => 'Nome', 'value' => old('name'), 'hint' => 'Onde o token será usado, como “CI do projeto”.'])

        <fieldset class="checks" @error('abilities') aria-describedby="abilities-error" @enderror>
            <legend class="field-label">Permissões</legend>
            @foreach ($abilities as $ability)
                <label class="check">
                    <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, (array) old('abilities', []), true))>
                    <span>{{ $ability }}</span>
                </label>
            @endforeach
            <p class="field-hint">Limitam o que o token faz em seu nome; nunca dão mais do que seus perfis permitem.</p>
            @error('abilities')
                <p id="abilities-error" class="field-error">{{ $message }}</p>
            @enderror
            @error('abilities.*')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </fieldset>

        @php($selectedLifetime = (int) old('lifetime_hours', $defaultLifetime))
        <div class="field">
            <label for="lifetime_hours">Validade</label>
            <select id="lifetime_hours" name="lifetime_hours" @error('lifetime_hours') aria-invalid="true" aria-describedby="lifetime_hours-error" @enderror>
                @foreach ($lifetimes as $hours)
                    <option value="{{ $hours }}" @selected($selectedLifetime === $hours)>{{ $hours }} {{ $hours === 1 ? 'hora' : 'horas' }}</option>
                @endforeach
            </select>
            @error('lifetime_hours')
                <p id="lifetime_hours-error" class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Criar token</button>
            <a class="btn" href="{{ route('admin.tokens.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
