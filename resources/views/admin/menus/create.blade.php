@extends('layouts.admin-shell')

@section('title', 'Novo menu')

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.menus.index') }}">← Menus</a>
            <h1>Novo menu</h1>
        </div>
    </div>

    <form class="form" method="POST" action="{{ route('admin.menus.store') }}" novalidate>
        @csrf

        @include('admin.menus.partials.form', ['menu' => null])

        <p class="field-hint">
            Um menu novo cria uma área de permissão. Só o Administrador a recebe automaticamente; os demais perfis
            precisam dela na matriz.
        </p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Criar menu</button>
            <a class="btn" href="{{ route('admin.menus.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
