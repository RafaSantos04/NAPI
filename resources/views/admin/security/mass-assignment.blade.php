@extends('layouts.admin-shell')

@use('App\Domain\Security\Enums\SecurityTestScenario')
@use('App\Models\SecurityTestRun')

@section('title', 'Mass Assignment')

{{-- Three columns need more room than the reading width of the other pages. --}}
@section('content-class', 'content-wide')

@php
    // After a run the form keeps the same document and payload, so the other
    // scenario can be run against them. On a first visit the protected
    // property is attempted, which is the case the test is about.
    $context = $result?->result_context ?? [];
    $selectedTarget = old('target_resource_id', $result?->target_resource_id ?? $resources->first()?->id);
    $selectedScenario = old('scenario', $result?->scenario->value ?? SecurityTestScenario::Vulnerable->value);
    $newName = old('payload.name', $context['after']['name'] ?? 'Documento renomeado');
    // An unchecked box sends nothing, so old() alone cannot tell "unchecked"
    // from "first visit".
    $attemptApproval = session()->hasOldInput()
        ? (bool) old('payload.is_approved')
        : in_array('is_approved', $context['attempted'] ?? ['is_approved'], true);
@endphp

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.security.index') }}">← Security Lab</a>
            <h1>Mass Assignment</h1>
        </div>
    </div>

    <p class="lead">
        O dono de um documento o renomeia e envia, no mesmo payload, uma propriedade que essa operação não deveria
        alterar. No cenário vulnerável, tudo o que chega é atribuído ao model. No protegido, a operação repassa só as
        propriedades do seu contrato. Os recursos são documentos sintéticos, restaurados depois de cada execução.
    </p>

    {{-- Configuration, result of the last run, history. On narrow screens the
         result moves above the form (see .lab in admin.css). --}}
    <div class="lab">
        <section class="lab-config" aria-labelledby="config-title">
            <h2 id="config-title">Configuração do teste</h2>

            @can('create', SecurityTestRun::class)
                @if ($resources->isEmpty())
                    <p class="empty">
                        Não há dados sintéticos. Rode <code>php artisan db:seed --class=SecurityLabSeeder</code>.
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.security.mass-assignment.run') }}" novalidate>
                        @csrf

                        <fieldset class="choices" aria-describedby="target-hint @error('target_resource_id') target-error @enderror">
                            <legend>Documento alvo</legend>
                            <div class="choice-list">
                                @foreach ($resources as $resource)
                                    <label class="choice">
                                        <input type="radio" name="target_resource_id" value="{{ $resource->id }}" required
                                               @checked($selectedTarget === $resource->id)>
                                        <span>
                                            {{ $resource->name }}
                                            <span class="choice-note">actor: {{ $resource->owner?->name ?? 'removido' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="target-hint" class="field-hint">
                                O actor é o dono do documento: ele pode editá-lo. A sua sessão continua sendo a sua.
                            </p>
                            @error('target_resource_id')
                                <p id="target-error" class="field-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="choices" @error('payload') aria-describedby="payload-error" @enderror>
                            <legend>Payload enviado pelo actor</legend>

                            <div class="field">
                                <label for="payload-name">
                                    <code>name</code> <span class="badge">permitida</span>
                                </label>
                                <input id="payload-name" name="payload[name]" type="text" value="{{ $newName }}"
                                       maxlength="100" autocomplete="off" required
                                       @error('payload.name') aria-invalid="true" aria-describedby="payload-name-error" @enderror>
                                @error('payload.name')
                                    <p id="payload-name-error" class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <label class="choice">
                                <input type="checkbox" name="payload[is_approved]" value="1" @checked($attemptApproval)
                                       aria-describedby="approval-hint @error('payload.is_approved') approval-error @enderror">
                                <span>
                                    <code>is_approved = true</code> <span class="badge badge-protected">protegida</span>
                                </span>
                            </label>
                            <p id="approval-hint" class="field-hint">
                                Aprovar cabe a uma revisão, não a quem renomeia. Desmarque para enviar só a alteração legítima.
                            </p>
                            @error('payload.is_approved')
                                <p id="approval-error" class="field-error">{{ $message }}</p>
                            @enderror
                            @error('payload')
                                <p id="payload-error" class="field-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="segmented" @error('scenario') aria-describedby="scenario-error" @enderror>
                            <legend>Cenário</legend>
                            <div class="segmented-options">
                                @foreach (SecurityTestScenario::cases() as $scenario)
                                    <label>
                                        <input class="sr-only" type="radio" name="scenario" value="{{ $scenario->value }}"
                                               @checked($selectedScenario === $scenario->value)>
                                        <span>{{ $scenario->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('scenario')
                                <p id="scenario-error" class="field-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <button type="submit" class="btn btn-primary btn-block">Executar teste</button>
                    </form>
                @endif
            @else
                <p class="muted">A sua conta pode consultar o histórico, mas não executar testes.</p>
            @endcan
        </section>

        <section class="lab-result @if (! $result) is-empty @endif" aria-labelledby="result-title">
            <h2 id="result-title">Resultado</h2>

            @if ($result)
                {{-- Says which run this is: the one just executed or one
                     picked in the history. --}}
                <p class="result-meta muted">
                    Execução de {{ $result->created_at->format('d/m/Y H:i') }}
                    · por {{ $result->operator?->name ?? 'operador removido' }}
                </p>
                @include('admin.security.partials.mass-assignment-result', ['run' => $result])
            @else
                <p class="lab-placeholder">O resultado do teste aparece aqui depois da execução.</p>
            @endif
        </section>

        <div class="lab-history">
            <h2 id="history-title">Histórico</h2>

            @if ($runs->isEmpty())
                <p class="lab-placeholder">Nenhuma execução ainda.</p>
            @else
                {{-- Fixed-height box with its own scroll. tabindex makes it
                     focusable, which is what lets the keyboard scroll it. --}}
                <section class="runs-scroll" aria-labelledby="history-title" tabindex="0">
                    <ol class="runs">
                        @foreach ($runs as $run)
                            <li class="run">
                                {{-- Opens this run in the result panel. --}}
                                <a class="run-link" href="{{ request()->fullUrlWithQuery(['run' => $run->id]) }}#result-title"
                                   @if ($result?->is($run)) aria-current="true" @endif>
                                    <div class="run-head">
                                        <span class="verdict verdict-{{ $run->security_verdict->value }}">{{ $run->security_verdict->label() }}</span>
                                        <time datetime="{{ $run->created_at->toIso8601String() }}">{{ $run->created_at->format('d/m H:i') }}</time>
                                    </div>
                                    <p>@include('admin.security.partials.mass-assignment-outcome', ['run' => $run])</p>
                                    <p class="muted">
                                        {{ $run->actor?->name ?? 'actor removido' }} → {{ $run->target?->name ?? 'recurso removido' }}
                                    </p>
                                    <p class="muted">por {{ $run->operator?->name ?? 'operador removido' }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{ $runs->links('admin.partials.pagination') }}
            @endif
        </div>
    </div>
@endsection
