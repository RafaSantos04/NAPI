{{-- Signed-in admin area: header, sidebar with the sections the user's
     Policies allow ($adminNavigation, from a view composer) and flash
     messages. Pages extend this and fill `content`. --}}
@extends('layouts.admin')

@section('header')
    <a class="brand" href="{{ route('admin.home') }}">{{ config('app.name', 'NAPI') }} <span>Admin</span></a>

    <div class="header-end">
        <span class="muted">{{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn">Sair</button>
        </form>
    </div>
@endsection

@section('sidebar')
    <ul>
        @foreach ($adminNavigation as $item)
            <li>
                <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
            </li>
        @endforeach
    </ul>
@endsection

@push('scripts')
    <script>
        // Forms with data-confirm ask before submitting (e.g. deactivation).
        document.addEventListener('submit', (event) => {
            const message = event.target.dataset.confirm;

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    </script>
@endpush
