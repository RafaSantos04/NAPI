@extends('layouts.admin-shell')

@use('App\Models\MenuProfile')

@section('title', $menu->label)

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.menus.index') }}">← Menus</a>
            <h1>{{ $menu->label }}</h1>
        </div>

        <div class="page-actions">
            @can('delete', $menu)
                <form method="POST" action="{{ route('admin.menus.destroy', $menu) }}"
                      data-confirm="Excluir o menu {{ $menu->label }}? @if ($grantingProfiles > 0){{ $grantingProfiles }} {{ $grantingProfiles === 1 ? 'perfil perde' : 'perfis perdem' }} as permissões desta área. @endif @if ($menu->is_system)É um menu de sistema: até ele ser recriado, a área {{ $menu->key }} fica só para o Administrador.@endif">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Excluir<span class="sr-only"> {{ $menu->label }}</span></button>
                </form>
            @endcan
            @can('update', $menu)
                <a class="btn" href="{{ route('admin.menus.edit', $menu) }}">Editar</a>
            @endcan
        </div>
    </div>

    <dl class="details">
        <dt>Chave</dt>
        <dd><code>{{ $menu->key }}</code></dd>

        <dt>Permissões</dt>
        <dd>
            @foreach (MenuProfile::ACTIONS as $action)
                <code>{{ $menu->permissionFor($action) }}</code>
            @endforeach
        </dd>

        <dt>Perfis com permissão</dt>
        <dd>{{ $grantingProfiles }}</dd>

        <dt>Rota</dt>
        <dd>{{ $menu->route_name }}</dd>

        <dt>Ícone</dt>
        <dd>{{ $menu->icon ?: '—' }}</dd>

        <dt>Menu pai</dt>
        <dd>
            @if ($menu->parent)
                <a href="{{ route('admin.menus.show', $menu->parent) }}">{{ $menu->parent->label }}</a>
            @else
                — (raiz)
            @endif
        </dd>

        <dt>Submenus</dt>
        <dd>
            @forelse ($menu->children as $child)
                <a href="{{ route('admin.menus.show', $child) }}">{{ $child->label }}</a>@unless ($loop->last), @endunless
            @empty
                —
            @endforelse
        </dd>

        <dt>Ordem</dt>
        <dd>{{ $menu->order }}</dd>

        <dt>Status</dt>
        <dd>@include('admin.users.partials.status', ['active' => $menu->is_active])</dd>

        <dt>Tipo</dt>
        <dd>@include('admin.menus.partials.kind', ['menu' => $menu])</dd>

        <dt>Descrição</dt>
        <dd>{{ $menu->description ?: '—' }}</dd>
    </dl>

    @if ($menu->is_system)
        <p class="notice">
            Menu de sistema: as rotas da aplicação usam a chave <code>{{ $menu->key }}</code>. Só o Administrador pode
            excluí-lo e, enquanto ele não for recriado (<code>php artisan db:seed --class=MenuSeeder</code>), a área fica
            só para o Administrador; as permissões dos perfis nela não voltam sozinhas. Rótulo, rota, posição e status
            podem ser alterados.
        </p>
    @endif

    @if ($menu->children->isNotEmpty())
        <p class="notice">Tem submenus: só pode ser excluído depois que eles forem movidos ou excluídos.</p>
    @endif
@endsection
