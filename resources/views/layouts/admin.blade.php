<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Admin') · {{ config('app.name', 'NAPI') }}</title>

    {{-- Plain CSS on purpose: the shell renders without a Vite build. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        :root {
            --bg: #000;
            --surface: #0a0a0a;
            --border: #262626;
            --text: #ededec;
            --muted: #a1a09a;
            --danger: #f87171;
            --focus: #ededec;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }

        html, body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text); }

        .admin-header {
            position: fixed;
            inset: 0 0 auto 0;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
        }

        .admin-main { min-height: 100vh; }

        .btn {
            display: inline-block;
            padding: .4rem 1rem;
            font: inherit;
            font-size: .875rem;
            color: var(--text);
            background: transparent;
            border: 1px solid var(--border);
            border-radius: .375rem;
            cursor: pointer;
            list-style: none;
        }
        .btn:hover { border-color: var(--muted); }
        .btn-primary { width: 100%; background: var(--text); color: var(--bg); border-color: var(--text); }
        .btn-primary:hover { background: #fff; }

        :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }

        .muted { color: var(--muted); font-size: .875rem; }

        @stack('styles')
    </style>
</head>
<body>
    <header class="admin-header">
        @yield('header')
    </header>

    <main class="admin-main">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
