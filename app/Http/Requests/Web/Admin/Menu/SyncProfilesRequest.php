<?php

namespace App\Http\Requests\Web\Admin\Menu;

use App\Http\Requests\Web\Admin\Concerns\AdminValidationMessages;
use App\Models\Menu;
use App\Models\Profile;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One menu's row of the matrix, for the profiles on the form. Same shape and
 * flags as SyncMenusRequest, keyed by profile instead of by menu.
 */
class SyncProfilesRequest extends FormRequest
{
    use AdminValidationMessages;

    /**
     * A form whose rows are all read-only sends no profiles at all.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('profiles')) {
            $this->merge(['profiles' => []]);
        }
    }

    /**
     * No rule of its own: every profile whose row would change must pass
     * ProfilePolicy::syncMenus with the matrix it would end up with, as if
     * it were saved from the profile page (system profiles, own profile,
     * flags the actor does not hold). A profile that does not change is not
     * asked about, since it is not written.
     */
    public function authorize(): bool
    {
        $actor = $this->user();
        $menu = $this->route('menu');

        if ($actor === null || ! $menu instanceof Menu) {
            return false;
        }

        $requested = (array) $this->input('profiles', []);

        return Profile::whereKey(array_map('strval', array_keys($requested)))
            ->with('menus')
            ->get()
            ->every(function (Profile $profile) use ($actor, $menu, $requested) {
                $matrix = $profile->matrixWith($menu, (array) $requested[$profile->id]);

                return $matrix === $profile->matrix() || $actor->can('syncMenus', [$profile, $matrix]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profiles' => ['present', 'array', 'max:200', $this->knownProfileIds(...)],
            'profiles.*' => ['array', 'required_array_keys:can_view,can_create,can_update,can_delete'],
            'profiles.*.can_view' => ['boolean'],
            'profiles.*.can_create' => ['boolean'],
            'profiles.*.can_update' => ['boolean'],
            'profiles.*.can_delete' => ['boolean'],
        ];
    }

    private function knownProfileIds(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $ids = array_map('strval', array_keys($value));

        if (Profile::whereKey($ids)->count() !== count($ids)) {
            $fail('A lista tem perfis que não existem.');
        }
    }
}
