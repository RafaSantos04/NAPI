<?php

namespace App\Http\Requests\Web\Admin\Concerns;

/**
 * The API accepts partial updates; an admin edit form always sends every
 * field, so an emptied field is an error rather than "leave unchanged".
 * Fields that may be cleared (`nullable`) keep their rules.
 */
trait SendsEveryField
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function everyFieldRequired(array $rules): array
    {
        return array_map(
            fn (mixed $fieldRules) => is_array($fieldRules) && ! in_array('nullable', $fieldRules, true)
                ? array_map(fn (mixed $rule) => $rule === 'sometimes' ? 'required' : $rule, $fieldRules)
                : $fieldRules,
            $rules,
        );
    }
}
