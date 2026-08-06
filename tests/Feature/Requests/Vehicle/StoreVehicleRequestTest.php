<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Validator;

/**
 * `placa` and `chassi` both carry a `unique:vehicles,...` rule, so any test
 * that includes them touches the database (even a passing case still runs a
 * lookup query). That is why this coverage lives under `tests/Feature`
 * instead of `tests/Unit` — see `.claude/context/testing.md`.
 */
function existingVehicle(array $overrides = []): Vehicle
{
    $user = User::factory()->create();

    $vehicle = new Vehicle(array_merge([
        'placa'       => 'ABC1D23',
        'chassi'      => '9BWZZZ377VT004251',
        'marca'       => 'Volkswagen',
        'modelo'      => 'Gol',
        'versao'      => '1.0 MPI',
        'valor_venda' => '45000.99',
        'cor'         => 'Prata',
        'km'          => 12000,
        'cambio'      => Cambio::Manual,
        'combustivel' => Combustivel::Flex,
    ], $overrides));
    $vehicle->user_id = $user->id;
    $vehicle->save();

    return $vehicle;
}

function validStorePayload(array $overrides = []): array
{
    return array_merge([
        'placa'       => 'XYZ9A87',
        'chassi'      => '1HGCM82633A004352',
        'marca'       => 'Fiat',
        'modelo'      => 'Argo',
        'versao'      => '1.3',
        'valor_venda' => 60000.00,
        'cor'         => 'Branco',
        'km'          => 500,
        'cambio'      => 'automatico',
        'combustivel' => 'gasolina',
    ], $overrides);
}

it('passes validation for a fully valid, unique payload', function () {
    $validator = Validator::make(
        validStorePayload(),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->passes())->toBeTrue();
});

it('accepts a placa in the traditional format', function () {
    $validator = Validator::make(
        validStorePayload(['placa' => 'ABC1234']),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('placa'))->toBeFalse();
});

it('accepts a placa in the Mercosul format', function () {
    $validator = Validator::make(
        validStorePayload(['placa' => 'ABC1D23']),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('placa'))->toBeFalse();
});

it('accepts a lowercase placa because the format check is case-insensitive', function () {
    $validator = Validator::make(
        validStorePayload(['placa' => 'abc1d23']),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->errors()->has('placa'))->toBeFalse();
});

it('rejects a placa that does not match the required format', function () {
    $validator = Validator::make(
        validStorePayload(['placa' => '1234567']),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('placa'))
        ->toBe('A placa deve estar no formato Mercosul ou tradicional (ex.: ABC1D23 ou ABC1234).');
});

it('rejects a placa that already belongs to another vehicle', function () {
    $existing = existingVehicle();

    $validator = Validator::make(
        validStorePayload(['placa' => $existing->placa]),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('placa'))
        ->toBe('Já existe um veículo cadastrado com essa placa.');
});

it('rejects a chassi shorter than 17 characters', function () {
    $validator = Validator::make(
        validStorePayload(['chassi' => str_repeat('A', 16)]),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('O chassi deve ter exatamente 17 caracteres.');
});

it('rejects a chassi longer than 17 characters', function () {
    $validator = Validator::make(
        validStorePayload(['chassi' => str_repeat('A', 18)]),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('O chassi deve ter exatamente 17 caracteres.');
});

it('rejects a chassi containing non-alphanumeric characters', function () {
    $validator = Validator::make(
        validStorePayload(['chassi' => '9BWZZZ377VT00425-']),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('O chassi deve conter apenas letras e números.');
});

it('rejects a chassi that already belongs to another vehicle', function () {
    $existing = existingVehicle();

    $validator = Validator::make(
        validStorePayload(['chassi' => $existing->chassi]),
        (new StoreVehicleRequest())->rules(),
        (new StoreVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('Já existe um veículo cadastrado com esse chassi.');
});
