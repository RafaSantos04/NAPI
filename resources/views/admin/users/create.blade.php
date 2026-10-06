@extends('layouts.admin-shell')

@section('title', 'Novo usuário')

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.users.index') }}">← Usuários</a>
            <h1>Novo usuário</h1>
        </div>
    </div>

    <form class="form" method="POST" action="{{ route('admin.users.store') }}" novalidate>
        @csrf

        @include('admin.partials.field', ['name' => 'name', 'label' => 'Nome', 'value' => old('name'), 'autocomplete' => 'off'])
        @include('admin.partials.field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'off'])
        @include('admin.partials.field', ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'Mínimo de 8 caracteres.'])
        @include('admin.partials.field', ['name' => 'password_confirmation', 'label' => 'Confirmar senha', 'type' => 'password', 'autocomplete' => 'new-password'])

        <p class="field-hint">A conta é criada ativa e sem perfis. Atribua os perfis no detalhe do usuário.</p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Criar usuário</button>
            <a class="btn" href="{{ route('admin.users.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
