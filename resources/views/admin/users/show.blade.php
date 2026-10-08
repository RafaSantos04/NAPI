@extends('layouts.admin-shell')

@section('title', $user->name)

@section('content')
    <div class="page-header">
        <div>
            <a class="back" href="{{ route('admin.users.index') }}">← Usuários</a>
            <h1>{{ $user->name }}</h1>
        </div>

        <div class="page-actions">
            @can('delete', $user)
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                      data-confirm="Excluir {{ $user->name }}? A conta será removida da listagem e perderá o acesso. O histórico será preservado.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Excluir<span class="sr-only"> {{ $user->name }}</span></button>
                </form>
            @endcan
            @can('update', $user)
                <a class="btn" href="{{ route('admin.users.edit', $user) }}">Editar</a>
            @endcan

            @if ($user->is_active)
                @can('deactivate', $user)
                    <form method="POST" action="{{ route('admin.users.deactivate', $user) }}"
                          data-confirm="Desativar {{ $user->name }}? Os tokens de API e as sessões da conta serão encerrados.">
                        @csrf
                        <button type="submit" class="btn btn-danger">Desativar</button>
                    </form>
                @endcan
            @else
                @can('activate', $user)
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}">
                        @csrf
                        <button type="submit" class="btn">Ativar</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <dl class="details">
        <dt>E-mail</dt>
        <dd>{{ $user->email }}</dd>

        <dt>Status</dt>
        <dd>@include('admin.users.partials.status', ['active' => $user->is_active])</dd>

        <dt>Perfis</dt>
        <dd>{{ $user->profiles->pluck('name')->join(', ') ?: 'Nenhum' }}</dd>

        <dt>Tokens de API</dt>
        <dd>{{ $user->tokens_count }}</dd>

        <dt>Criado em</dt>
        <dd><time datetime="{{ $user->created_at->toIso8601String() }}">{{ $user->created_at->format('d/m/Y H:i') }}</time></dd>

        <dt>Atualizado em</dt>
        <dd><time datetime="{{ $user->updated_at->toIso8601String() }}">{{ $user->updated_at->format('d/m/Y H:i') }}</time></dd>
    </dl>

    @can('assignProfiles', $user)
        <section class="panel" aria-labelledby="profiles-title">
            <h2 id="profiles-title">Perfis</h2>

            <form method="POST" action="{{ route('admin.users.profiles.update', $user) }}">
                @csrf
                @method('PUT')

                @php($profilesError = $errors->first('profile_ids') ?: $errors->first('profile_ids.*'))

                <fieldset class="checks" @if ($profilesError) aria-describedby="profiles-error" @endif>
                    <legend class="sr-only">Perfis atribuídos a {{ $user->name }}</legend>

                    @foreach ($profiles as $profile)
                        <label class="check">
                            <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}" @checked($user->profiles->contains($profile))>
                            <span>{{ $profile->name }}</span>
                            @if ($profile->description)
                                <span class="muted">{{ $profile->description }}</span>
                            @endif
                        </label>
                    @endforeach
                </fieldset>

                @if ($profilesError)
                    <p id="profiles-error" class="field-error">{{ $profilesError }}</p>
                @endif

                <button type="submit" class="btn">Salvar perfis</button>
            </form>
        </section>
    @endcan
@endsection
