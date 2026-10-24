{{-- Editable attributes of a profile. The identifier of the administrator
     profile is shown, not sent, and "system profile" is a field only for the
     administrator; ProfileUpdateRequest refuses both otherwise. --}}
@php($administratorProfile = $profile?->isAdministrator() ?? false)

@include('admin.partials.field', ['name' => 'name', 'label' => 'Nome', 'value' => old('name', $profile?->name)])

@if ($administratorProfile)
    <div class="field">
        <span class="field-label">Identificador</span>
        <p class="field-static"><code>{{ $profile->slug }}</code></p>
        <p class="field-hint">Fixo: é por ele que o sistema reconhece o perfil que tem todas as permissões.</p>
    </div>
@else
    @include('admin.partials.field', [
        'name' => 'slug',
        'label' => 'Identificador',
        'value' => old('slug', $profile?->slug),
        'hint' => 'Letras, números, hífens e sublinhados. Não é chave de permissão: as permissões vêm da matriz.',
    ])
@endif

<div class="field">
    <label for="description">Descrição <span class="muted">(opcional)</span></label>
    <textarea id="description" name="description" rows="3" maxlength="500"
              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $profile?->description) }}</textarea>
    @error('description')
        <p id="description-error" class="field-error">{{ $message }}</p>
    @enderror
</div>

@if (auth()->user()->isAdministrator() && ! $administratorProfile)
    <div class="field">
        <input type="hidden" name="is_system" value="0">
        <label class="check">
            <input type="checkbox" name="is_system" value="1" @checked(old('is_system', $profile?->is_system ?? false))
                   aria-describedby="is_system-hint">
            <span>Perfil de sistema</span>
        </label>
        <p id="is_system-hint" class="field-hint">Só o Administrador edita, exclui ou altera a matriz de um perfil de sistema.</p>
        @error('is_system')
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
@endif
