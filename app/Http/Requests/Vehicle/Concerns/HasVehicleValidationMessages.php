<?php

namespace App\Http\Requests\Vehicle\Concerns;

/**
 * Shared between `StoreVehicleRequest` and `UpdateVehicleRequest`: both
 * validate the same set of domain fields, so the Portuguese messages that
 * surface through the 422 `errors` member are identical for either request.
 */
trait HasVehicleValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'placa.required' => 'O campo placa é obrigatório.',
            'placa.string' => 'O campo placa deve ser um texto.',
            'placa.regex' => 'A placa deve estar no formato Mercosul ou tradicional (ex.: ABC1D23 ou ABC1234).',
            'placa.unique' => 'Já existe um veículo cadastrado com essa placa.',

            'chassi.required' => 'O campo chassi é obrigatório.',
            'chassi.string' => 'O campo chassi deve ser um texto.',
            'chassi.size' => 'O chassi deve ter exatamente 17 caracteres.',
            'chassi.alpha_num' => 'O chassi deve conter apenas letras e números.',
            'chassi.unique' => 'Já existe um veículo cadastrado com esse chassi.',

            'marca.required' => 'O campo marca é obrigatório.',
            'marca.string' => 'O campo marca deve ser um texto.',

            'modelo.required' => 'O campo modelo é obrigatório.',
            'modelo.string' => 'O campo modelo deve ser um texto.',

            'versao.required' => 'O campo versão é obrigatório.',
            'versao.string' => 'O campo versão deve ser um texto.',

            'valor_venda.required' => 'O campo valor de venda é obrigatório.',
            'valor_venda.numeric' => 'O valor de venda deve ser numérico.',
            'valor_venda.min' => 'O valor de venda deve ser de, no mínimo, R$ 0,01.',

            'cor.required' => 'O campo cor é obrigatório.',
            'cor.string' => 'O campo cor deve ser um texto.',

            'km.required' => 'O campo quilometragem é obrigatório.',
            'km.integer' => 'A quilometragem deve ser um número inteiro.',
            'km.min' => 'A quilometragem não pode ser negativa.',

            'cambio.required' => 'O campo câmbio é obrigatório.',
            'cambio.enum' => 'O câmbio deve ser "manual" ou "automatico".',

            'combustivel.required' => 'O campo combustível é obrigatório.',
            'combustivel.enum' => 'O combustível deve ser um dos seguintes: gasolina, alcool, flex, diesel, hibrido ou eletrico.',
        ];
    }
}
