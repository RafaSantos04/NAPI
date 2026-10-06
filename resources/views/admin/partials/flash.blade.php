{{-- Feedback after an operation. Plain text only: never exception details. --}}
@if (session('status'))
    <p class="flash flash-success" role="status">{{ session('status') }}</p>
@endif

@if (session('error'))
    <p class="flash flash-error" role="alert">{{ session('error') }}</p>
@endif
