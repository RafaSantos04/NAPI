@extends('layouts.admin-shell')

@section('title', 'Início')
@section('content-class', 'content-wide')

@section('content')
    <div class="page-header dashboard-heading">
        <div>
            <p class="eyebrow">Workspace administrativo</p>
            <h1>Olá, {{ auth()->user()->name }}.</h1>
            <p class="lead">Escolha uma área autorizada para continuar.</p>
        </div>

        <span class="adapter-status">
            <span class="status-dot" aria-hidden="true"></span>
            Sessão web ativa
        </span>
    </div>

    <section aria-labelledby="areas-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Navegação derivada das Policies</p>
                <h2 id="areas-title">Áreas disponíveis</h2>
            </div>
            <span class="muted">{{ count($adminNavigation) }} autorizada{{ count($adminNavigation) === 1 ? '' : 's' }}</span>
        </div>

        <div class="area-grid">
            @foreach ($adminNavigation as $item)
                <a class="area-card" href="{{ route($item['route']) }}">
                    <span class="area-card-index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="area-card-copy">
                        <strong>{{ $item['label'] }}</strong>
                        <span>Abrir área autorizada</span>
                    </span>
                    <span class="area-card-arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="access-model" aria-labelledby="access-model-title">
        <div>
            <p class="eyebrow">Contrato de acesso</p>
            <h2 id="access-model-title">Cinco camadas, uma decisão explicável.</h2>
            <p class="muted">A interface projeta as permissões; ela não substitui as verificações do domínio.</p>
        </div>

        <ol class="access-layers">
            <li><span>01</span> Sessão ativa</li>
            <li><span>02</span> Acesso ao admin</li>
            <li><span>03</span> Permissão funcional</li>
            <li><span>04</span> Policy contextual</li>
            <li><span>05</span> Invariante de domínio</li>
        </ol>
    </section>
@endsection
