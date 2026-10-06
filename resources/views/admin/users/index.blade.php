@extends('layouts.admin-shell')

@section('title', 'Usuários')

@section('content')
    <div class="page-header">
        <h1>Usuários</h1>

        @can('create', App\Models\User::class)
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Novo usuário</a>
        @endcan
    </div>

    <form class="search" method="GET" action="{{ route('admin.users.index') }}" role="search">
        <label for="q" class="sr-only">Buscar por nome ou e-mail</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Buscar por nome ou e-mail" maxlength="100">
        <button type="submit" class="btn">Buscar</button>
        @if ($search !== '')
            <a class="btn" href="{{ route('admin.users.index') }}">Limpar</a>
        @endif
    </form>

    @if ($users->isEmpty())
        <p class="empty">
            @if ($search !== '')
                Nenhum usuário encontrado para “{{ $search }}”.
            @else
                Nenhum usuário cadastrado.
            @endif
        </p>
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">
                    Usuários, página {{ $users->currentPage() }} de {{ $users->lastPage() }}, {{ $users->total() }} no total
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">E-mail</th>
                        <th scope="col">Status</th>
                        <th scope="col">Perfis</th>
                        <th scope="col">Criado em</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <th scope="row">{{ $user->name }}</th>
                            <td>{{ $user->email }}</td>
                            <td>@include('admin.users.partials.status', ['active' => $user->is_active])</td>
                            <td>{{ $user->profiles->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>
                                <time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('d/m/Y') }}</time>
                            </td>
                            <td class="actions">
                                <a href="{{ route('admin.users.show', $user) }}">Ver<span class="sr-only"> {{ $user->name }}</span></a>
                                @can('update', $user)
                                    <a href="{{ route('admin.users.edit', $user) }}">Editar<span class="sr-only"> {{ $user->name }}</span></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $users->links('admin.partials.pagination') }}
    @endif
@endsection
