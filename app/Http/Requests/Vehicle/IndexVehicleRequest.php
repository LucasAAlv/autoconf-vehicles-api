<?php

namespace App\Http\Requests\Vehicle;

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
        ];
    }
}
