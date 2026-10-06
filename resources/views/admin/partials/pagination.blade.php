@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Paginação">
        @if ($paginator->onFirstPage())
            <span class="btn is-disabled" aria-disabled="true">Anterior</span>
        @else
            <a class="btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        <span class="muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
        @else
            <span class="btn is-disabled" aria-disabled="true">Próxima</span>
        @endif
    </nav>
@endif
