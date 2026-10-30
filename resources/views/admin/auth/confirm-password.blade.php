@extends('layouts.admin-shell')

@section('title', 'Confirmar senha')

@section('content')
    <div class="page-header">
        <h1>Confirmar senha</h1>
    </div>

    <p class="lead">Esta ação exige que você confirme a sua senha.</p>

    <form class="form" method="POST" action="{{ route('admin.password.confirm.store') }}" novalidate>
        @csrf

        {{-- Too many attempts come back under `email`, as on the login form
             (TooManyLoginAttemptsException). --}}
        @error('email')
            <p class="flash flash-error" role="alert">{{ $message }}</p>
        @enderror

        @include('admin.partials.field', ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'autocomplete' => 'current-password'])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Confirmar</button>
            <a class="btn" href="{{ route('admin.tokens.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
