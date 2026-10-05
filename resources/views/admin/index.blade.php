@extends('layouts.admin')

@section('header')
    @auth
        <span class="muted">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn">Sair</button>
        </form>
    @else
        @include('admin.auth.login-panel')
    @endauth
@endsection
