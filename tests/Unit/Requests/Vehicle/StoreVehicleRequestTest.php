<?php

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use Illuminate\Support\Facades\Validator;

/**
 * These tests deliberately never send `placa` or `chassi` in the payload:
 * both fields carry a `unique:vehicles,...` rule, and Laravel only runs a
 * non-implicit rule (like `unique`) for an attribute that is actually present
 * in the data. Omitting them keeps these tests free of any database access,
 * as required for `tests/Unit` (see `.claude/context/testing.md`). Coverage
 * for `placa`/`chassi` themselves (format, uniqueness) lives under
 * `tests/Feature/Requests/Vehicle/StoreVehicleRequestTest.php`.
 */
function storeRulesWithoutPlacaAndChassi(): array
{
    $rules = (new StoreVehicleRequest())->rules();

    return array_diff_key($rules, array_flip(['placa', 'chassi']));
}

it('always authorizes, deferring ownership checks to the policy built in a later issue', function () {
    expect((new StoreVehicleRequest())->authorize())->toBeTrue();
});

it('requires marca, modelo, versao and cor', function () {
    $validator = Validator::make(
        [],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('marca'))->toBe('O campo marca é obrigatório.');
    expect($validator->errors()->first('modelo'))->toBe('O campo modelo é obrigatório.');
    expect($validator->errors()->first('versao'))->toBe('O campo versão é obrigatório.');
    expect($validator->errors()->first('cor'))->toBe('O campo cor é obrigatório.');
});

it('rejects a valor_venda below the 0.01 minimum with a Portuguese message', function () {
    $validator = Validator::make(
        ['valor_venda' => 0],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('valor_venda'))
        ->toBe('O valor de venda deve ser de, no mínimo, R$ 0,01.');
});

it('accepts a valor_venda exactly at the 0.01 minimum', function () {
    $validator = Validator::make(
        ['valor_venda' => 0.01],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('valor_venda'))->toBeFalse();
});

it('rejects a negative km with a Portuguese message', function () {
    $validator = Validator::make(
        ['km' => -1],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('km'))->toBe('A quilometragem não pode ser negativa.');
});

it('accepts a km of exactly zero', function () {
    $validator = Validator::make(
        ['km' => 0],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('km'))->toBeFalse();
});

it('rejects a cambio value outside the enum with a Portuguese message', function () {
    $validator = Validator::make(
        ['cambio' => 'cvt'],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('cambio'))->toBe('O câmbio deve ser "manual" ou "automatico".');
});

it('accepts each valid cambio enum case', function (string $value) {
    $validator = Validator::make(
        ['cambio' => $value],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('cambio'))->toBeFalse();
})->with(['manual', 'automatico']);

it('rejects a combustivel value outside the enum with a Portuguese message', function () {
    $validator = Validator::make(
        ['combustivel' => 'nuclear'],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('combustivel'))
        ->toBe('O combustível deve ser um dos seguintes: gasolina, alcool, flex, diesel, hibrido ou eletrico.');
});

it('accepts each valid combustivel enum case', function (string $value) {
    $validator = Validator::make(
        ['combustivel' => $value],
        storeRulesWithoutPlacaAndChassi(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('combustivel'))->toBeFalse();
})->with(['gasolina', 'alcool', 'flex', 'diesel', 'hibrido', 'eletrico']);
