<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Validator;

/**
 * Same reasoning as the Store request's Feature test: `placa`/`chassi` carry
 * a `unique:vehicles,...` rule, so exercising them needs the database.
 */
function createVehicle(array $overrides = []): Vehicle
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

it('passes when the payload keeps the same placa and chassi as the vehicle being updated', function () {
    $vehicle = createVehicle();

    $validator = Validator::make(
        ['placa' => $vehicle->placa, 'chassi' => $vehicle->chassi],
        (new UpdateVehicleRequest())->rulesIgnoring($vehicle->id),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeFalse();
});

it('rejects a placa that belongs to a different vehicle', function () {
    $other = createVehicle(['placa' => 'XYZ9A87', 'chassi' => '1HGCM82633A004352']);
    $vehicle = createVehicle(['placa' => 'ABC1D23', 'chassi' => '9BWZZZ377VT004251']);

    $validator = Validator::make(
        ['placa' => $other->placa],
        (new UpdateVehicleRequest())->rulesIgnoring($vehicle->id),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('placa'))
        ->toBe('Já existe um veículo cadastrado com essa placa.');
});

it('rejects a chassi that belongs to a different vehicle', function () {
    $other = createVehicle(['placa' => 'XYZ9A87', 'chassi' => '1HGCM82633A004352']);
    $vehicle = createVehicle(['placa' => 'ABC1D23', 'chassi' => '9BWZZZ377VT004251']);

    $validator = Validator::make(
        ['chassi' => $other->chassi],
        (new UpdateVehicleRequest())->rulesIgnoring($vehicle->id),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('Já existe um veículo cadastrado com esse chassi.');
});

it('rejects a placa format violation even when other fields are omitted', function () {
    $vehicle = createVehicle();

    $validator = Validator::make(
        ['placa' => 'not-a-plate'],
        (new UpdateVehicleRequest())->rulesIgnoring($vehicle->id),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('placa'))
        ->toBe('A placa deve estar no formato Mercosul ou tradicional (ex.: ABC1D23 ou ABC1234).');
});

it('rejects a chassi that is not exactly 17 alphanumeric characters', function () {
    $vehicle = createVehicle();

    $validator = Validator::make(
        ['chassi' => 'TOOSHORT'],
        (new UpdateVehicleRequest())->rulesIgnoring($vehicle->id),
        (new UpdateVehicleRequest())->messages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('chassi'))
        ->toBe('O chassi deve ter exatamente 17 caracteres.');
});

it('derives the ignored vehicle from the bound "vehicle" route parameter', function () {
    $vehicle = createVehicle();

    $request = UpdateVehicleRequest::create(
        "/vehicles/{$vehicle->id}",
        'PUT',
        ['placa' => $vehicle->placa],
    );
    $route = new class ($vehicle->id) {
        public function __construct(private readonly int $id)
        {
        }

        public function parameter($name, $default = null)
        {
            return $name === 'vehicle' ? $this->id : $default;
        }
    };
    $request->setRouteResolver(fn () => $route);

    $validator = Validator::make(
        $request->all(),
        $request->rules(),
        $request->messages(),
    );

    expect($validator->fails())->toBeFalse();
});
