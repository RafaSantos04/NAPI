@extends('layouts.admin-shell')

@section('title', 'Novo perfil')

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.profiles.index') }}">← Perfis</a>
            <h1>Novo perfil</h1>
        </div>
    </div>

    <form class="form" method="POST" action="{{ route('admin.profiles.store') }}" novalidate>
        @csrf

        @include('admin.profiles.partials.form', ['profile' => null])

        <p class="field-hint">O perfil é criado sem permissões. Defina a matriz no detalhe do perfil.</p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Criar perfil</button>
            <a class="btn" href="{{ route('admin.profiles.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
