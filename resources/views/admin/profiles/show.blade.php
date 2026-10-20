@extends('layouts.admin-shell')

@use('App\Models\MenuProfile')

@section('title', $profile->name)

@section('content-class', 'content-wide')

@php
    $actions = ['view' => 'Ver', 'create' => 'Criar', 'update' => 'Alterar', 'delete' => 'Excluir'];
    $actor = auth()->user();
    // The Policy itself answers whether the actor could save this matrix as
    // it is now: system profile, own profile, a flag the actor lacks.
    $editable = $actor->can('syncMenus', [$profile, $matrix]);
@endphp

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.profiles.index') }}">← Perfis</a>
            <h1>{{ $profile->name }}</h1>
        </div>

        <div class="page-actions">
            @can('delete', $profile)
                <form method="POST" action="{{ route('admin.profiles.destroy', $profile) }}"
                      data-confirm="Excluir o perfil {{ $profile->name }}? As permissões dele serão removidas.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Excluir<span class="sr-only"> {{ $profile->name }}</span></button>
                </form>
            @endcan
            @can('update', $profile)
                <a class="btn" href="{{ route('admin.profiles.edit', $profile) }}">Editar</a>
            @endcan
        </div>
    </div>

    <dl class="details">
        <dt>Identificador</dt>
        <dd><code>{{ $profile->slug }}</code></dd>

        <dt>Descrição</dt>
        <dd>{{ $profile->description ?: '—' }}</dd>

        <dt>Tipo</dt>
        <dd>@include('admin.profiles.partials.kind', ['profile' => $profile])</dd>

        <dt>Usuários</dt>
        <dd>{{ $profile->users_count }}</dd>
    </dl>

    @if ($profile->is_system)
        <p class="notice">Perfil de sistema: não pode ser editado nem excluído, e a matriz dele não muda.</p>
    @elseif ($profile->users_count > 0)
        <p class="notice">Atribuído a {{ $profile->users_count }} {{ $profile->users_count === 1 ? 'usuário' : 'usuários' }}: só pode ser excluído depois de retirado de todos.</p>
    @endif

    <section class="panel" aria-labelledby="matrix-title">
        <h2 id="matrix-title">Permissões</h2>

        @if ($profile->isAdministrator())
            <p class="muted">
                O Administrador tem todas as permissões funcionais, inclusive das áreas criadas depois, sem depender da
                matriz. As Policies e as regras de domínio continuam valendo para ele.
            </p>
        @else
            <p class="muted">
                Cada célula é uma permissão <code>{chave}.{ação}</code>. A chave do menu é a identidade da permissão:
                mudar rótulo ou rota não muda o que o perfil pode fazer.
                @if (! $editable)
                    Você pode consultar, mas não alterar esta matriz.
                @endif
            </p>

            @if ($editable)
                <form method="POST" action="{{ route('admin.profiles.permissions.update', $profile) }}">
                    @csrf
                    @method('PUT')
            @endif

            <div class="table-wrap">
                <table class="table matrix">
                    <caption class="sr-only">Permissões do perfil {{ $profile->name }} por área</caption>
                    <thead>
                        <tr>
                            <th scope="col">Área e chave</th>
                            @foreach ($actions as $label)
                                <th scope="col" class="matrix-cell">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($menus as ['menu' => $menu, 'depth' => $depth])
                            <tr>
                                <th scope="row">
                                    <span class="tree-label" style="--depth: {{ $depth }}">
                                        {{ $menu->label }}
                                        @unless ($menu->is_active)
                                            <span class="badge badge-inactive">Inativo</span>
                                        @endunless
                                        <code class="matrix-key">{{ $menu->key }}</code>
                                    </span>
                                </th>
                                @foreach ($actions as $action => $label)
                                    @php
                                        $field = "permissions[{$menu->id}][can_{$action}]";
                                        $granted = $matrix[$menu->id]["can_{$action}"] ?? false;
                                        $permission = $menu->permissionFor($action);
                                    @endphp
                                    <td class="matrix-cell">
                                        @if ($editable)
                                            {{-- The hidden "0" is overridden by the box when checked. A
                                                 flag the actor does not hold cannot be granted by them
                                                 (the Policy refuses it); the box only says so early. --}}
                                            <input type="hidden" name="{{ $field }}" value="0">
                                            <input type="checkbox" name="{{ $field }}" value="1" @checked($granted)
                                                   @disabled(! $actor->hasPermission($permission))
                                                   aria-label="{{ $label }} em {{ $menu->label }} ({{ $permission }})">
                                        @elseif ($granted)
                                            <span aria-hidden="true">✓</span><span class="sr-only">{{ $permission }}: concedida</span>
                                        @else
                                            <span class="muted" aria-hidden="true">—</span><span class="sr-only">{{ $permission }}: não concedida</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($editable)
                    @if ($errors->has('permissions') || $errors->has('permissions.*'))
                        <p class="field-error">{{ $errors->first('permissions') ?: $errors->first('permissions.*') }}</p>
                    @endif
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar permissões</button>
                    </div>
                </form>
            @endif
        @endif
    </section>
@endsection
