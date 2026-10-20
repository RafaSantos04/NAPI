@extends('layouts.admin-shell')

@section('title', 'Editar '.$profile->name)

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.profiles.show', $profile) }}">← {{ $profile->name }}</a>
            <h1>Editar perfil</h1>
        </div>
    </div>

    {{-- Only the profile's own data: the matrix has its own form. --}}
    <form class="form" method="POST" action="{{ route('admin.profiles.update', $profile) }}" novalidate>
        @csrf
        @method('PUT')

        @include('admin.profiles.partials.form', ['profile' => $profile])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a class="btn" href="{{ route('admin.profiles.show', $profile) }}">Cancelar</a>
        </div>
    </form>

    <p class="field-hint">
        As permissões do perfil ficam na <a href="{{ route('admin.profiles.show', $profile) }}#matrix-title">matriz, no detalhe do perfil</a>.
    </p>
@endsection
