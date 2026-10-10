@extends('layouts.admin-shell')

@section('title', 'Segurança')

@section('content')
    <div class="page-header">
        <h1>Security Lab</h1>
    </div>

    <p class="lead">
        Testes controlados que executam a versão vulnerável e a versão protegida de uma mesma operação,
        lado a lado. Todos operam apenas sobre dados sintéticos, nunca sobre usuários, perfis ou tokens reais.
    </p>

    <ul class="lab-tests">
        <li>
            <a href="{{ route('admin.security.idor.show') }}">IDOR / BOLA</a>
            <span class="muted">Um actor pede, pelo identificador, um recurso que pertence a outra pessoa.</span>
        </li>
        <li>
            <a href="{{ route('admin.security.mass-assignment.show') }}">Mass Assignment</a>
            <span class="muted">O dono de um recurso envia, junto com uma alteração permitida, uma propriedade que não cabe a ele mudar.</span>
        </li>
    </ul>
@endsection
