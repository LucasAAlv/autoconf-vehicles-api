<?php

namespace App\Http\Requests\Vehicle;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Http\Requests\Vehicle\Concerns\HasVehicleValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    use HasVehicleValidationMessages;

    /**
     * Always authorizes: owner/admin authorization for creating a vehicle is
     * `VehiclePolicy`'s job, wired up together with the controller and
     * routes in a later issue, not this one. This request is validation-only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'placa' => [
                'required',
                'string',
                'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/i',
                Rule::unique('vehicles', 'placa'),
            ],
            'chassi' => [
                'required',
                'string',
                'size:17',
                'alpha_num',
                Rule::unique('vehicles', 'chassi'),
            ],
            'marca' => ['required', 'string'],
            'modelo' => ['required', 'string'],
            'versao' => ['required', 'string'],
            'valor_venda' => ['required', 'numeric', 'min:0.01'],
            'cor' => ['required', 'string'],
            'km' => ['required', 'integer', 'min:0'],
            'cambio' => ['required', Rule::enum(Cambio::class)],
            'combustivel' => ['required', Rule::enum(Combustivel::class)],
        ];
    }
}
