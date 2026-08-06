<?php

use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Same reasoning as the Store request's unit test: `placa`/`chassi` are
 * omitted from every payload here so the `unique` rule (implicit-only
 * attributes are validated when absent) never fires, keeping these tests
 * free of database access. See
 * `tests/Feature/Requests/Vehicle/UpdateVehicleRequestTest.php` for the
 * `placa`/`chassi` coverage that does need the database.
 */
function updateRulesIgnoringWithoutPlacaAndChassi(): array
{
    $rules = (new UpdateVehicleRequest())->rulesIgnoring(null);

    return array_diff_key($rules, array_flip(['placa', 'chassi']));
}

it('always authorizes, deferring ownership checks to the policy built in a later issue', function () {
    expect((new UpdateVehicleRequest())->authorize())->toBeTrue();
});

it('passes validation when no optional fields are provided at all', function () {
    $validator = Validator::make(
        [],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects an empty marca when the field is present but blank', function () {
    $validator = Validator::make(
        ['marca' => ''],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('marca'))->toBe('O campo marca é obrigatório.');
});

it('rejects a valor_venda below the 0.01 minimum when provided', function () {
    $validator = Validator::make(
        ['valor_venda' => 0],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('valor_venda'))
        ->toBe('O valor de venda deve ser de, no mínimo, R$ 0,01.');
});

it('rejects a negative km when provided', function () {
    $validator = Validator::make(
        ['km' => -1],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('km'))->toBe('A quilometragem não pode ser negativa.');
});

it('rejects a cambio value outside the enum when provided', function () {
    $validator = Validator::make(
        ['cambio' => 'cvt'],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('cambio'))->toBe('O câmbio deve ser "manual" ou "automatico".');
});

it('rejects a combustivel value outside the enum when provided', function () {
    $validator = Validator::make(
        ['combustivel' => 'nuclear'],
        updateRulesIgnoringWithoutPlacaAndChassi(),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('combustivel'))
        ->toBe('O combustível deve ser um dos seguintes: gasolina, alcool, flex, diesel, hibrido ou eletrico.');
});
