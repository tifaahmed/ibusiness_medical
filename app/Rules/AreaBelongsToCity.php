<?php

namespace App\Rules;

use App\Models\Area;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * A branch's optional area must be one of the city the branch is in. The city
 * is the sibling `city_id` of the attribute being checked, so it works for a
 * flat payload (`area_id` / `city_id`) and a nested one
 * (`branches.2.area_id` / `branches.2.city_id`) alike. An empty value passes:
 * the area is optional.
 */
class AreaBelongsToCity implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $area = Area::query()->find($value, ['id', 'city_id']);
        if ($area === null) {
            $fail('The selected area is invalid.');

            return;
        }

        $cityId = Arr::get($this->data, preg_replace('/area_id$/', 'city_id', $attribute));
        if ($cityId === null || $cityId === '' || (int) $area->city_id !== (int) $cityId) {
            $fail('The selected area does not belong to the chosen city.');
        }
    }
}
