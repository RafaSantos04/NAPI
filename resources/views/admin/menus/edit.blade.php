@extends('layouts.admin-shell')

@section('title', 'Editar '.$menu->label)

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.menus.show', $menu) }}">← {{ $menu->label }}</a>
            <h1>Editar menu</h1>
        </div>
    </div>

    <form class="form" method="POST" action="{{ route('admin.menus.update', $menu) }}" novalidate>
        @csrf
        @method('PUT')

        @include('admin.menus.partials.form', ['menu' => $menu])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a class="btn" href="{{ route('admin.menus.show', $menu) }}">Cancelar</a>
        </div>
    </form>

    @include('admin.menus.partials.profiles', ['menu' => $menu, 'profiles' => $profiles])
@endsection
