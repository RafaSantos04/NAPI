@extends('layouts.admin-shell')

@section('title', 'Token criado')

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.tokens.index') }}">← Meus tokens</a>
            <h1>Token criado</h1>
        </div>
    </div>

    {{-- The only time the plain-text token is shown: it is not stored
         anywhere it could be read back, not even in the session. --}}
    <p class="lead">Copie o token agora. Ele não será exibido de novo; se perder, revogue e crie outro.</p>

    <dl class="details">
        <dt>Nome</dt>
        <dd>{{ $token->accessToken->name }}</dd>

        <dt>Permissões</dt>
        <dd>{{ implode(', ', $token->accessToken->abilities) }}</dd>

        <dt>Expira em</dt>
        <dd>
            <time datetime="{{ $token->accessToken->expires_at->toIso8601String() }}">{{ $token->accessToken->expires_at->format('d/m/Y H:i') }}</time>
        </dd>

        <dt>Token</dt>
        <dd><code>{{ $token->plainTextToken }}</code></dd>
    </dl>

    <div class="form-actions">
        <a class="btn btn-primary" href="{{ route('admin.tokens.index') }}">Concluir</a>
    </div>
@endsection
