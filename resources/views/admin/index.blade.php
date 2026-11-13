{{-- Guest landing: the public edge of the internal backoffice. --}}
@extends('layouts.admin')

@section('header')
    @include('admin.partials.brand')

    <div class="header-end guest-header-status">
        <span class="status-dot" aria-hidden="true"></span>
        <span>Área administrativa</span>
    </div>
@endsection

@section('content')
    <div class="guest-page">
        <section class="guest-intro" aria-labelledby="guest-title">
            <p class="eyebrow">Backoffice interno · acesso restrito</p>
            <h1 id="guest-title">Governança e segurança operacional sem atalhos.</h1>
            <p class="lead">
                O admin NAPI usa sessão web, CSRF e as mesmas Policies, Actions e invariantes do domínio.
                Tokens Bearer continuam isolados na API.
            </p>

            <ul class="trust-list" aria-label="Controles de acesso">
                <li><span aria-hidden="true">01</span> Identidade ativa e sessão protegida</li>
                <li><span aria-hidden="true">02</span> Permissão funcional e Policy contextual</li>
                <li><span aria-hidden="true">03</span> Invariantes de domínio preservadas</li>
            </ul>
        </section>

        <section class="guest-login" aria-labelledby="login-title">
            <p class="eyebrow">Identificação do operador</p>
            <h2 id="login-title">Acessar o NAPI</h2>
            <p class="muted">Use as credenciais da sua conta administrativa.</p>
            @include('admin.auth.login-panel', ['expanded' => true])
        </section>
    </div>
@endsection
