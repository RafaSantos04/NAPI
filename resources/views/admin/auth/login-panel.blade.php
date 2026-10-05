{{-- Dropdown anchored to the top-right button. <details> gives the toggle and
     keyboard support without JS; the script below only adds focus, Escape
     and click-outside. Opens by itself when the last attempt failed. --}}
<details class="login" id="login-panel" {{ $errors->any() ? 'open' : '' }}>
    <summary class="btn">Entrar</summary>

    <div class="login-panel">
        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <div class="field">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                >
                @error('email')
                    <p id="email-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Senha</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
                @error('password')
                    <p id="password-error" class="field-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Entrar</button>
        </form>
    </div>
</details>

@push('styles')
    .login { position: relative; }
    .login > summary::-webkit-details-marker { display: none; }

    .login-panel {
        position: absolute;
        top: calc(100% + .5rem);
        right: 0;
        width: min(18rem, calc(100vw - 2.5rem));
        padding: 1.25rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: .5rem;
    }

    .field { margin-bottom: 1rem; }
    .field label { display: block; margin-bottom: .375rem; font-size: .8125rem; color: var(--muted); }
    .field input {
        width: 100%;
        padding: .5rem .625rem;
        font: inherit;
        font-size: .875rem;
        color: var(--text);
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: .375rem;
    }
    .field input:focus-visible { outline-offset: 0; }
    .field input[aria-invalid="true"] { border-color: var(--danger); }
    .field-error { margin: .375rem 0 0; font-size: .8125rem; color: var(--danger); }
@endpush

@push('scripts')
    <script>
        (() => {
            const panel = document.getElementById('login-panel');
            const toggle = panel.querySelector('summary');

            panel.addEventListener('toggle', () => {
                if (panel.open) {
                    panel.querySelector('#email').focus();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && panel.open) {
                    panel.open = false;
                    toggle.focus();
                }
            });

            document.addEventListener('click', (event) => {
                if (panel.open && !panel.contains(event.target)) {
                    panel.open = false;
                }
            });
        })();
    </script>
@endpush
