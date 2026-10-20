{{-- Editable attributes of a profile. `is_system` is never a form field:
     system profiles come from the seeder. --}}
@include('admin.partials.field', ['name' => 'name', 'label' => 'Nome', 'value' => old('name', $profile?->name)])
@include('admin.partials.field', [
    'name' => 'slug',
    'label' => 'Identificador',
    'value' => old('slug', $profile?->slug),
    'hint' => 'Letras, números, hífens e sublinhados. Não é chave de permissão: as permissões vêm da matriz.',
])

<div class="field">
    <label for="description">Descrição <span class="muted">(opcional)</span></label>
    <textarea id="description" name="description" rows="3" maxlength="500"
              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $profile?->description) }}</textarea>
    @error('description')
        <p id="description-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
