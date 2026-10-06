@extends('layouts.admin-shell')

@section('title', 'Editar '.$user->name)

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.users.show', $user) }}">← {{ $user->name }}</a>
            <h1>Editar usuário</h1>
        </div>
    </div>

    {{-- Only name and e-mail: status and profiles have their own flows. --}}
    <form class="form" method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
        @csrf
        @method('PUT')

        @include('admin.partials.field', ['name' => 'name', 'label' => 'Nome', 'value' => old('name', $user->name), 'autocomplete' => 'off'])
        @include('admin.partials.field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => old('email', $user->email), 'autocomplete' => 'off'])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a class="btn" href="{{ route('admin.users.show', $user) }}">Cancelar</a>
        </div>
    </form>
@endsection
