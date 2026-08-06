<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class IndexVehicleRequest extends FormRequest
{
    /**
     * Always authorizes: owner/admin authorization has no bearing on
     * listing (`VehiclePolicy::viewAny` already allows any authenticated
     * user), so this request is validation-only, matching
     * Store/UpdateVehicleRequest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `page`/`per_page` are both optional — the controller falls back to a
     * default and caps `per_page` on its own — but when present they must be
     * positive integers.
     *
     * `q`, `marca`, `modelo` and `placa` are all optional free-text filters:
     * `Vehicle::scopeFilter()` is the one that decides how each is matched
     * against the query, this request only checks they are strings when
     * present.
     *
     * `sort` (issue #31) is a comma-separated list of fields, each optionally
     * prefixed with `-` for descending order (e.g. `km,-valor_venda`). The
     * inline closure rule strips that prefix off each token and rejects the
     * whole request with a 422 the moment any one of them isn't a key of
     * `Vehicle::SORTABLE_COLUMNS` — the same allow-list `Vehicle::scopeSort()`
     * uses to build the query — so a client never gets a silently-ignored
     * unknown sort field, and the query never sees an unlisted one either.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'q'        => ['sometimes', 'string'],
            'marca'    => ['sometimes', 'string'],
            'modelo'   => ['sometimes', 'string'],
            'placa'    => ['sometimes', 'string'],
            'sort'     => ['sometimes', 'string', $this->sortRule()],
        ];
    }

    /**
     * Closure rule backing the `sort` field: splits the comma-separated
     * value and fails validation as soon as one token's field name (after
     * stripping a leading `-`) isn't a key of `Vehicle::SORTABLE_COLUMNS`.
     */
    private function sortRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            foreach (explode(',', (string) $value) as $token) {
                $field = str_starts_with($token, '-') ? substr($token, 1) : $token;

                if ($field === '' || ! array_key_exists($field, Vehicle::SORTABLE_COLUMNS)) {
                    $fail("The {$attribute} field contains an unsupported sort field: \"{$token}\".");

                    return;
                }
            }
        };
    }
}
