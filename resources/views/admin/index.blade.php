{{-- Guest landing: black page, only the login button. --}}
@extends('layouts.admin')

@section('header')
    <div class="header-end">
        @include('admin.auth.login-panel')
    </div>
@endsection
