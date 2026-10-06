@extends('layouts.admin-shell')

@section('title', 'Início')

@section('content')
    <h1>Olá, {{ auth()->user()->name }}.</h1>
    <p class="muted">Escolha uma área no menu.</p>
@endsection
