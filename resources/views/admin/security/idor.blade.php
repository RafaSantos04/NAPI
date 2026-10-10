@extends('layouts.admin-shell')

@use('App\Domain\Security\Enums\SecurityTestScenario')
@use('App\Models\SecurityTestRun')

@section('title', 'IDOR / BOLA')

{{-- Three columns need more room than the reading width of the other pages. --}}
@section('content-class', 'content-wide')

@php
    // After a run the form keeps the same pair, so the other scenario can be
    // run against it. On a first visit the target defaults to a resource of
    // another persona, which is the case the test is about.
    $selectedActor = old('actor_user_id', $result?->acting_as_user_id ?? $actors->first()?->id);
    $selectedTarget = old('target_resource_id', $result?->target_resource_id
        ?? $resources->firstWhere('owner_user_id', '!=', $selectedActor)?->id);
    $selectedScenario = old('scenario', $result?->scenario->value ?? SecurityTestScenario::Vulnerable->value);
@endphp

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.security.index') }}">← Security Lab</a>
            <h1>IDOR / BOLA</h1>
        </div>
    </div>

    <p class="lead">
        Um actor pede um recurso pelo identificador. No cenário vulnerável, encontrar o recurso basta para devolvê-lo.
        No protegido, uma Policy verifica antes se o actor é o dono. Os recursos são documentos sintéticos.
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
                    <form method="POST" action="{{ route('admin.security.idor.run') }}" novalidate>
                        @csrf

                        <fieldset class="choices" aria-describedby="actor-hint @error('actor_user_id') actor-error @enderror">
                            <legend>Actor</legend>
                            <div class="choice-list">
                                @foreach ($actors as $actor)
                                    <label class="choice">
                                        <input type="radio" name="actor_user_id" value="{{ $actor->id }}" required
                                               @checked($selectedActor === $actor->id)>
                                        <span>{{ $actor->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="actor-hint" class="field-hint">Identidade simulada pelo teste. A sua sessão continua sendo a sua.</p>
                            @error('actor_user_id')
                                <p id="actor-error" class="field-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="choices" @error('target_resource_id') aria-describedby="target-error" @enderror>
                            <legend>Recurso alvo</legend>
                            <div class="choice-list">
                                @foreach ($resources as $resource)
                                    <label class="choice">
                                        <input type="radio" name="target_resource_id" value="{{ $resource->id }}" required
                                               @checked($selectedTarget === $resource->id)>
                                        <span>
                                            {{ $resource->name }}
                                            <span class="choice-note">dono: {{ $resource->owner?->name ?? 'removido' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('target_resource_id')
                                <p id="target-error" class="field-error">{{ $message }}</p>
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
                @include('admin.security.partials.result', ['run' => $result])
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
                                <div class="run-head">
                                    <span class="verdict verdict-{{ $run->security_verdict->value }}">{{ $run->security_verdict->label() }}</span>
                                    <time datetime="{{ $run->created_at->toIso8601String() }}">{{ $run->created_at->format('d/m H:i') }}</time>
                                </div>
                                <p>@include('admin.security.partials.outcome', ['run' => $run])</p>
                                <p class="muted">
                                    {{ $run->actor?->name ?? 'actor removido' }} → {{ $run->target?->name ?? 'recurso removido' }}
                                    @if ($run->target?->owner)
                                        (dono: {{ $run->target->owner->name }})
                                    @endif
                                </p>
                                <p class="muted">por {{ $run->operator?->name ?? 'operador removido' }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{ $runs->links('admin.partials.pagination') }}
            @endif
        </div>
    </div>
@endsection
