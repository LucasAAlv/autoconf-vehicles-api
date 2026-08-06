<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Http\Requests\Vehicle\Concerns\HasVehicleValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    use HasVehicleValidationMessages;

    /**
     * Always authorizes: owner/admin authorization for updating a vehicle is
     * `VehiclePolicy`'s job, wired up together with the controller and
     * routes in a later issue, not this one. This request is validation-only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Assumes the eventual route parameter is named `vehicle` (i.e.
     * `PUT|PATCH /vehicles/{vehicle}`), since routing does not exist yet at
     * the time this request was built (that's issue #19). Confirm/adjust
     * this name once the routes land.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rulesIgnoring($this->route('vehicle'));
    }

    /**
     * The rule set extracted with the ignored record passed in directly,
     * so it can be unit/feature-tested without a bound route: pass the
     * vehicle id (or the `Vehicle` model instance) to exclude from the
     * uniqueness checks.
     *
     * Every field is `sometimes` so a partial (`PATCH`-style) payload is
     * valid; a field that is present but empty still fails as `required`.
     *
     * @return array<string, mixed>
     */
    public function rulesIgnoring(mixed $ignore): array
    {
        return [
            'placa' => [
                'sometimes',
                'required',
                'string',
                'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/i',
                Rule::unique('vehicles', 'placa')->ignore($ignore),
            ],
            'chassi' => [
                'sometimes',
                'required',
                'string',
                'size:17',
                'alpha_num',
                Rule::unique('vehicles', 'chassi')->ignore($ignore),
            ],
            'marca' => ['sometimes', 'required', 'string'],
            'modelo' => ['sometimes', 'required', 'string'],
            'versao' => ['sometimes', 'required', 'string'],
            'valor_venda' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'cor' => ['sometimes', 'required', 'string'],
            'km' => ['sometimes', 'required', 'integer', 'min:0'],
            'cambio' => ['sometimes', 'required', Rule::enum(Cambio::class)],
            'combustivel' => ['sometimes', 'required', Rule::enum(Combustivel::class)],
        ];
    }
}
