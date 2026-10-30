@extends('layouts.admin-shell')

@section('title', 'Meus tokens')

@section('content')
    <div class="page-header">
        <h1>Meus tokens</h1>

        <a class="btn btn-primary" href="{{ route('admin.tokens.create') }}">Novo token</a>
    </div>

    <p class="lead">Tokens de API da sua conta. Uma conta com acesso à área administrativa só obtém token por aqui, com a senha confirmada.</p>

    @if ($tokens->isEmpty())
        <p class="empty">Nenhum token ativo.</p>
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">
                    Tokens, página {{ $tokens->currentPage() }} de {{ $tokens->lastPage() }}, {{ $tokens->total() }} no total
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Permissões</th>
                        <th scope="col">Criado em</th>
                        <th scope="col">Expira em</th>
                        <th scope="col"><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $token)
                        <tr>
                            <th scope="row">{{ $token->name }}</th>
                            <td>{{ implode(', ', $token->abilities) }}</td>
                            <td>
                                <time datetime="{{ $token->created_at->toIso8601String() }}">{{ $token->created_at->format('d/m/Y H:i') }}</time>
                            </td>
                            <td>
                                @if ($token->expires_at)
                                    <time datetime="{{ $token->expires_at->toIso8601String() }}">{{ $token->expires_at->format('d/m/Y H:i') }}</time>
                                @else
                                    <span class="muted">Validade global</span>
                                @endif
                            </td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.tokens.destroy', $token->id) }}"
                                      data-confirm="Revogar o token {{ $token->name }}? Quem o usa perde o acesso na hora.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Revogar<span class="sr-only"> {{ $token->name }}</span></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $tokens->links('admin.partials.pagination') }}
    @endif
@endsection
