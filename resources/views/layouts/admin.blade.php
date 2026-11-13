<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0e14">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Admin') · {{ config('app.name', 'NAPI') }}</title>

    {{-- Plain CSS on purpose: the area renders without a Vite build. --}}
    <link rel="icon" href="{{ asset('napi-mark.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
    @hasSection('sidebar')
        <a class="skip-link btn" href="#content">Ir para o conteúdo</a>
    @endif

    <header class="admin-header @hasSection('sidebar') has-border @endif">
        @yield('header')
    </header>

    @hasSection('sidebar')
        <div class="admin-shell">
            <nav class="admin-sidebar" aria-label="Administração">
                @yield('sidebar')
            </nav>

            <main class="admin-main" id="content" tabindex="-1">
                <div class="content @yield('content-class')">
                    @include('admin.partials.flash')
                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main class="admin-main guest-main" id="content">
            @yield('content')
        </main>
    @endif

    @stack('scripts')
</body>
</html>
