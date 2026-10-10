{{-- Outcome of the run just executed ($run). The content shown on EXPOSED is
     the synthetic document the actor should not have received. --}}
@use('App\Domain\Security\Enums\SecurityTestObservedOutcome')
@use('App\Domain\Security\Enums\SecurityTestVerdict')

@php
    $actor = $run->actor?->name ?? 'O actor';
    $document = $run->target?->name ?? 'o recurso';
    $owner = $run->target?->owner?->name ?? 'outra pessoa';
@endphp

<div class="result result-{{ $run->security_verdict->value }}" role="status">
    <p class="result-head">
        <span class="verdict verdict-{{ $run->security_verdict->value }}">{{ $run->security_verdict->label() }}</span>
        <span class="muted">@include('admin.security.partials.outcome', ['run' => $run])</span>
    </p>

    @if ($run->security_verdict === SecurityTestVerdict::Exposed)
        <p>
            {{ $actor }} não é dono de “{{ $document }}”, que pertence a {{ $owner }}, e mesmo assim recebeu o conteúdo.
            O recurso foi encontrado pelo identificador e devolvido sem verificar o dono.
        </p>

        @if ($run->target)
            <div class="disclosed">
                <p class="muted">Conteúdo devolvido ao actor</p>
                <p><strong>{{ $run->target->name }}</strong></p>
                <p>{{ $run->target->content }}</p>
            </div>
        @endif
    @elseif ($run->security_verdict === SecurityTestVerdict::Inconclusive)
        <p>
            A execução falhou por um erro técnico ({{ $run->result_context['error'] ?? 'desconhecido' }}).
            Isso não diz nada sobre a segurança: o resultado é inconclusivo.
        </p>
    @elseif ($run->observed_outcome === SecurityTestObservedOutcome::Denied)
        <p>
            {{ $actor }} pediu “{{ $document }}”, que pertence a {{ $owner }}. O recurso foi encontrado, mas a Policy
            <code>SecurityLabResourcePolicy::view</code> negou a leitura: encontrar não é autorizar.
        </p>
    @else
        <p>
            {{ $actor }} é dono de “{{ $document }}”. É um acesso legítimo, permitido nos dois cenários, e não demonstra IDOR.
            Escolha o recurso de outra persona para testar o controle.
        </p>
    @endif
</div>
