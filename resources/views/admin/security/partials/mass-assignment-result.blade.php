{{-- Outcome of the Mass Assignment run just executed ($run). The state shown
     comes from the run itself: the document was restored when it ended. --}}
@use('App\Domain\Security\Actions\RunMassAssignmentTest')
@use('App\Domain\Security\Enums\SecurityTestObservedOutcome')
@use('App\Domain\Security\Enums\SecurityTestVerdict')

@php
    $context = $run->result_context ?? [];
    $actor = $run->actor?->name ?? 'O actor';
    $document = $context['before']['name'] ?? 'o documento';
    $show = fn (mixed $value) => is_bool($value) ? ($value ? 'true' : 'false') : $value;
@endphp

<div class="result result-{{ $run->security_verdict->value }}" role="status">
    <p class="result-head">
        <span class="verdict verdict-{{ $run->security_verdict->value }}">{{ $run->security_verdict->label() }}</span>
        <span class="muted">@include('admin.security.partials.mass-assignment-outcome', ['run' => $run])</span>
    </p>

    @if ($run->security_verdict === SecurityTestVerdict::Exposed)
        <p>
            {{ $actor }} pode renomear “{{ $document }}”, mas não aprová-lo. O payload trouxe
            @foreach ($context['protected_changed'] as $property)
                <code>{{ $property }}</code>
            @endforeach
            e a atribuição aceitou tudo o que chegou: a propriedade protegida foi alterada por um dado controlado pelo cliente.
        </p>
    @elseif ($run->security_verdict === SecurityTestVerdict::Inconclusive)
        <p>
            A execução falhou por um erro técnico ({{ $context['error'] ?? 'desconhecido' }}).
            Isso não diz nada sobre a segurança: o resultado é inconclusivo.
        </p>
    @elseif ($run->observed_outcome === SecurityTestObservedOutcome::Denied)
        <p>
            O mesmo payload chegou, mas a operação repassou só o que está no seu contrato:
            @foreach (RunMassAssignmentTest::EDITABLE as $property)
                <code>{{ $property }}</code>.
            @endforeach
            A propriedade protegida foi ignorada e continuou como estava: existir no model não é poder alterar.
        </p>
    @else
        <p>
            O payload só trazia a propriedade permitida. É uma alteração legítima, aplicada nos dois cenários, e não
            demonstra Mass Assignment. Marque a propriedade protegida para testar o controle.
        </p>
    @endif

    @isset($context['before'])
        <div class="table-wrap state">
            <table class="table">
                <caption class="sr-only">Estado das propriedades observadas, antes e depois da escrita</caption>
                <thead>
                    <tr>
                        <th scope="col">Propriedade</th>
                        <th scope="col">Enviada</th>
                        <th scope="col">Antes</th>
                        <th scope="col">Depois</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($context['before'] as $property => $before)
                        @php
                            $after = $context['after'][$property];
                            $violated = in_array($property, $context['protected_changed'], true);
                        @endphp
                        <tr>
                            <th scope="row">
                                <code>{{ $property }}</code>
                                @if (in_array($property, RunMassAssignmentTest::EDITABLE, true))
                                    <span class="badge">permitida</span>
                                @else
                                    <span class="badge badge-protected">protegida</span>
                                @endif
                            </th>
                            <td>{{ in_array($property, $context['attempted'], true) ? 'sim' : 'não' }}</td>
                            <td>{{ $show($before) }}</td>
                            <td @class(['is-violation' => $violated])>
                                {{ $show($after) }}
                                @if ($violated)
                                    <span class="sr-only">(alteração indevida)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="muted">
            O documento já voltou ao estado de “Antes”: a escrita roda numa transação que é desfeita depois de observada.
        </p>
    @endisset
</div>
