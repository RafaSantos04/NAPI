@props([
    'href' => null,
    'icon',
    'label',
    'tooltip' => null,
    'type' => 'button',
    'variant' => 'default',
])

@php
    $classes = 'icon-action'.($variant === 'danger' ? ' icon-action-danger' : '');
    $tooltipText = $tooltip ?? $label;
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        aria-label="{{ $label }}"
        data-tooltip="{{ $tooltipText }}"
        title="{{ $tooltipText }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @switch($icon)
            @case('view')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                    <circle cx="12" cy="12" r="2.75" />
                </svg>
                @break

            @case('edit')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m14.5 5.5 4 4M4 20l4.5-1 10-10a2.8 2.8 0 0 0-4-4l-10 10L4 20Z" />
                    <path d="m13.5 6.5 4 4" />
                </svg>
                @break

            @case('delete')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5" />
                </svg>
                @break
        @endswitch
    </a>
@else
    <button
        type="{{ $type }}"
        aria-label="{{ $label }}"
        data-tooltip="{{ $tooltipText }}"
        title="{{ $tooltipText }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @switch($icon)
            @case('view')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                    <circle cx="12" cy="12" r="2.75" />
                </svg>
                @break

            @case('edit')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="m14.5 5.5 4 4M4 20l4.5-1 10-10a2.8 2.8 0 0 0-4-4l-10 10L4 20Z" />
                    <path d="m13.5 6.5 4 4" />
                </svg>
                @break

            @case('delete')
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v5m4-5v5" />
                </svg>
                @break
        @endswitch
    </button>
@endif
