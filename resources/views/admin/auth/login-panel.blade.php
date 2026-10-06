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

            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>
    </div>
</details>

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
