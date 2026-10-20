{{-- $menu is null on create. The key is only a field there: afterwards it
     is shown, not sent, and the request refuses it anyway (`prohibited`). --}}
@if ($menu === null)
    @include('admin.partials.field', [
        'name' => 'key',
        'label' => 'Chave',
        'value' => old('key'),
        'hint' => 'Identidade das permissões da área ({chave}.view, .create, .update, .delete). Minúsculas, números e hífens. Não pode ser alterada depois.',
    ])
@else
    <div class="field">
        <span class="field-label">Chave</span>
        <p class="field-static"><code>{{ $menu->key }}</code></p>
        <p class="field-hint">Imutável: é a identidade das permissões desta área.</p>
    </div>
@endif

@include('admin.partials.field', ['name' => 'label', 'label' => 'Rótulo', 'value' => old('label', $menu?->label)])
@include('admin.partials.field', [
    'name' => 'route_name',
    'label' => 'Rota',
    'value' => old('route_name', $menu?->route_name),
    'hint' => 'Navegação. Mudar a rota não muda nenhuma permissão.',
])
@include('admin.partials.field', ['name' => 'icon', 'label' => 'Ícone', 'value' => old('icon', $menu?->icon), 'required' => false])

@php($selectedParent = old('parent_id', $menu?->parent_id))
<div class="field">
    <label for="parent_id">Menu pai</label>
    <select id="parent_id" name="parent_id" @error('parent_id') aria-invalid="true" aria-describedby="parent_id-error" @enderror>
        <option value="">— Nenhum (menu raiz) —</option>
        @foreach ($parents as ['menu' => $option, 'depth' => $depth])
            <option value="{{ $option->id }}" @selected($selectedParent === $option->id)>{{ str_repeat('— ', $depth) }}{{ $option->label }} ({{ $option->key }})</option>
        @endforeach
    </select>
    @error('parent_id')
        <p id="parent_id-error" class="field-error">{{ $message }}</p>
    @enderror
</div>

@include('admin.partials.field', ['name' => 'order', 'label' => 'Ordem', 'type' => 'number', 'value' => old('order', $menu?->order ?? 0)])

<div class="field">
    <input type="hidden" name="is_active" value="0">
    <label class="check">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu?->is_active ?? true))
               aria-describedby="is_active-hint">
        <span>Ativo</span>
    </label>
    <p id="is_active-hint" class="field-hint">Inativo, o menu some da árvore de navegação. As permissões da chave continuam valendo.</p>
</div>

<div class="field">
    <label for="description">Descrição <span class="muted">(opcional)</span></label>
    <textarea id="description" name="description" rows="3" maxlength="500"
              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $menu?->description) }}</textarea>
    @error('description')
        <p id="description-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
