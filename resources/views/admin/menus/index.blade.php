@extends('layouts.admin-shell')

@section('title', 'Menus')

@section('content-class', 'content-wide')

@section('content')
    <div class="page-header">
        <h1>Menus</h1>

        @can('create', App\Models\Menu::class)
            <a class="btn btn-primary" href="{{ route('admin.menus.create') }}">Novo menu</a>
        @endcan
    </div>

    <p class="lead">
        A <strong>chave</strong> de um menu é a identidade das permissões da área (<code>{chave}.view</code>,
        <code>.create</code>, <code>.update</code>, <code>.delete</code>) e não muda. Rótulo, rota, posição e status
        são navegação: definem a árvore servida em <code>/api/v1/menus/tree</code>, não o acesso.
    </p>

    @if ($menus === [])
        <p class="empty">Nenhum menu cadastrado.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">Menus em ordem de navegação, com submenus abaixo do menu pai</caption>
                <thead>
                    <tr>
                        <th scope="col">Rótulo</th>
                        <th scope="col">Chave</th>
                        <th scope="col">Rota</th>
                        <th scope="col">Pai</th>
                        <th scope="col">Ordem</th>
                        <th scope="col">Status</th>
                        <th scope="col">Tipo</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($menus as ['menu' => $menu, 'depth' => $depth])
                        <tr>
                            <th scope="row"><span class="tree-label" style="--depth: {{ $depth }}">{{ $menu->label }}</span></th>
                            <td><code>{{ $menu->key }}</code></td>
                            <td>{{ $menu->route_name }}</td>
                            <td>{{ $menu->parent?->label ?? '—' }}</td>
                            <td>{{ $menu->order }}</td>
                            <td>@include('admin.users.partials.status', ['active' => $menu->is_active])</td>
                            <td>@include('admin.menus.partials.kind', ['menu' => $menu])</td>
                            <td class="actions">
                                <a href="{{ route('admin.menus.show', $menu) }}">Ver<span class="sr-only"> {{ $menu->label }}</span></a>
                                @can('update', $menu)
                                    <a href="{{ route('admin.menus.edit', $menu) }}">Editar<span class="sr-only"> {{ $menu->label }}</span></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
