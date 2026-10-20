@extends('layouts.admin-shell')

@section('title', 'Perfis')

@section('content')
    <div class="page-header">
        <h1>Perfis</h1>

        @can('create', App\Models\Profile::class)
            <a class="btn btn-primary" href="{{ route('admin.profiles.create') }}">Novo perfil</a>
        @endcan
    </div>

    <p class="lead">
        Um perfil define capacidades funcionais: a matriz diz, por área, o que quem tem o perfil pode ver, criar,
        alterar e excluir. As Policies e as regras de domínio continuam valendo por cima dela.
    </p>

    @if ($profiles->isEmpty())
        <p class="empty">Nenhum perfil cadastrado.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">
                    Perfis, página {{ $profiles->currentPage() }} de {{ $profiles->lastPage() }}, {{ $profiles->total() }} no total
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Identificador</th>
                        <th scope="col">Descrição</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Usuários</th>
                        <th scope="col">Permissões</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($profiles as $profile)
                        <tr>
                            <th scope="row">{{ $profile->name }}</th>
                            <td><code>{{ $profile->slug }}</code></td>
                            <td>{{ Str::limit($profile->description ?? '', 60) ?: '—' }}</td>
                            <td>@include('admin.profiles.partials.kind', ['profile' => $profile])</td>
                            <td>{{ $profile->users_count }}</td>
                            <td>{{ $profile->isAdministrator() ? 'todas (implícito)' : $profile->permissions_count }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.profiles.show', $profile) }}">Ver<span class="sr-only"> {{ $profile->name }}</span></a>
                                @can('update', $profile)
                                    <a href="{{ route('admin.profiles.edit', $profile) }}">Editar<span class="sr-only"> {{ $profile->name }}</span></a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $profiles->links('admin.partials.pagination') }}
    @endif
@endsection
