{{-- Signed-in admin area: header, sidebar with the sections the user's
     Policies allow ($adminNavigation, from a view composer) and flash
     messages. Pages extend this and fill `content`. --}}
@extends('layouts.admin')

@section('header')
    @include('admin.partials.brand')

    <div class="adapter-status" aria-label="Adapter de autenticação atual">
        <span class="status-dot" aria-hidden="true"></span>
        <span>Sessão web</span>
    </div>

    <div class="header-end">
        <span class="actor">
            <span class="actor-label">Operador autenticado</span>
            <span class="actor-email">{{ auth()->user()->email }}</span>
        </span>
        {{-- Self-service, not a section: AdminNavigation decides who is
             privileged, so a link everyone sees cannot live there. --}}
        <a href="{{ route('admin.tokens.index') }}" @if (request()->routeIs('admin.tokens.*')) aria-current="page" @endif>Meus tokens</a>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn">Sair</button>
        </form>
    </div>
@endsection

@section('sidebar')
    <div class="sidebar-heading">
        <span>Áreas autorizadas</span>
        <span class="badge badge-active">{{ count($adminNavigation) }}</span>
    </div>

    <ul>
        @foreach ($adminNavigation as $item)
            <li>
                <a href="{{ route($item['route']) }}" @if (request()->routeIs($item['active'])) aria-current="page" @endif>
                    <span class="nav-index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="sidebar-foot">
        <span>Autorização em camadas</span>
        <span class="mono">ADR-0008</span>
    </div>
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
