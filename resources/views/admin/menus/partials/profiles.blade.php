{{-- The permission matrix seen from this menu: one row per profile, the
     four actions on this menu's key. Saving goes through the same Policy
     and Action as the matrix on each profile's page. --}}
@php
    $actions = ['view' => 'Ver', 'create' => 'Criar', 'update' => 'Alterar', 'delete' => 'Excluir'];
    $actor = auth()->user();
    $rows = $profiles->map(function ($profile) use ($actor, $menu) {
        $matrix = $profile->matrix();

        return [
            'profile' => $profile,
            'flags' => $matrix[$menu->id] ?? [],
            // The question the profile page asks: could the actor save this
            // profile's matrix as it is? System profiles, own profile and
            // flags the actor lacks all make the row read-only.
            'editable' => $actor->can('syncMenus', [$profile, $matrix]),
        ];
    });
    $anyEditable = $rows->contains('editable', true);
@endphp

<section class="panel" aria-labelledby="profiles-title">
    <h2 id="profiles-title">Perfis com permissão nesta área</h2>

    <p class="muted">
        Cada linha é a linha deste menu na matriz de um perfil (<code>{{ $menu->key }}.{ação}</code>). Desmarcar as
        quatro ações desvincula o perfil do menu. É a mesma matriz do detalhe de cada perfil, com as mesmas regras.
        @unless ($anyEditable)
            Você pode consultar, mas não alterar estes vínculos.
        @endunless
    </p>

    @if ($anyEditable)
        <form method="POST" action="{{ route('admin.menus.permissions.update', $menu) }}">
            @csrf
            @method('PUT')
    @endif

    <div class="table-wrap">
        <table class="table matrix">
            <caption class="sr-only">Permissões de cada perfil em {{ $menu->label }}</caption>
            <thead>
                <tr>
                    <th scope="col">Perfil</th>
                    @foreach ($actions as $label)
                        <th scope="col" class="matrix-cell">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as ['profile' => $profile, 'flags' => $flags, 'editable' => $editable])
                    <tr>
                        <th scope="row">
                            {{ $profile->name }}
                            @include('admin.profiles.partials.kind', ['profile' => $profile])
                        </th>
                        @if ($profile->isAdministrator())
                            <td colspan="4" class="muted">todas, implicitamente</td>
                        @else
                            @foreach ($actions as $action => $label)
                                @php
                                    $field = "profiles[{$profile->id}][can_{$action}]";
                                    $granted = $flags["can_{$action}"] ?? false;
                                    $permission = $menu->permissionFor($action);
                                @endphp
                                <td class="matrix-cell">
                                    @if ($editable)
                                        {{-- Same hidden "0" + checkbox pair as the profile matrix. --}}
                                        <input type="hidden" name="{{ $field }}" value="0">
                                        <input type="checkbox" name="{{ $field }}" value="1" @checked($granted)
                                               @disabled(! $actor->hasPermission($permission))
                                               aria-label="{{ $label }} para {{ $profile->name }} ({{ $permission }})">
                                    @elseif ($granted)
                                        <span aria-hidden="true">✓</span><span class="sr-only">{{ $permission }}: concedida a {{ $profile->name }}</span>
                                    @else
                                        <span class="muted" aria-hidden="true">—</span><span class="sr-only">{{ $permission }}: não concedida a {{ $profile->name }}</span>
                                    @endif
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($anyEditable)
            @if ($errors->has('profiles') || $errors->has('profiles.*'))
                <p class="field-error">{{ $errors->first('profiles') ?: $errors->first('profiles.*') }}</p>
            @endif
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar perfis</button>
            </div>
        </form>
    @endif
</section>
