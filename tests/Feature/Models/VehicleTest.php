<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

it('creates a valid, persisted vehicle with the expected attribute casts', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Volkswagen',
        'modelo'       => 'Gol',
        'versao'       => '1.0 MPI',
        'valor_venda'  => '45000.99',
        'cor'          => 'Prata',
        'km'           => 12000,
        'cambio'       => Cambio::Manual,
        'combustivel'  => Combustivel::Flex,
    ]);
    $vehicle->user_id = $user->id;
    $vehicle->save();

    expect($vehicle->exists)->toBeTrue()
        ->and(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeTrue()
        ->and($vehicle->placa)->toBe('ABC1D23')
        ->and($vehicle->chassi)->toBe('9BWZZZ377VT004251')
        ->and($vehicle->cambio)->toBeInstanceOf(Cambio::class)
        ->and($vehicle->cambio)->toBe(Cambio::Manual)
        ->and($vehicle->combustivel)->toBeInstanceOf(Combustivel::class)
        ->and($vehicle->combustivel)->toBe(Combustivel::Flex)
        ->and($vehicle->km)->toBeInt()
        ->and($vehicle->user_id)->toBe($user->id);
});

it('only allows the domain fields to be mass assigned', function () {
    expect((new Vehicle())->getFillable())->toBe([
        'placa',
        'chassi',
        'marca',
        'modelo',
        'versao',
        'valor_venda',
        'cor',
        'km',
        'cambio',
        'combustivel',
    ]);
});

it('does not mass assign user_id', function () {
    $attacker = User::factory()->create();

    $vehicle = new Vehicle([
        'user_id'      => $attacker->id,
        'placa'        => 'ABC1D23',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Volkswagen',
        'modelo'       => 'Gol',
        'versao'       => '1.0 MPI',
        'valor_venda'  => '45000.99',
        'cor'          => 'Prata',
        'km'           => 12000,
        'cambio'       => Cambio::Manual,
        'combustivel'  => Combustivel::Flex,
    ]);

    expect($vehicle->user_id)->toBeNull();
});

it('belongs to the user referenced by user_id', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Volkswagen',
        'modelo'       => 'Gol',
        'versao'       => '1.0 MPI',
        'valor_venda'  => '45000.99',
        'cor'          => 'Prata',
        'km'           => 12000,
        'cambio'       => Cambio::Manual,
        'combustivel'  => Combustivel::Flex,
    ]);
    $vehicle->user_id = $user->id;
    $vehicle->save();

    expect($vehicle->user->is($user))->toBeTrue();
});

it('deletes the vehicle when its owning user is deleted', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Volkswagen',
        'modelo'       => 'Gol',
        'versao'       => '1.0 MPI',
        'valor_venda'  => '45000.99',
        'cor'          => 'Prata',
        'km'           => 12000,
        'cambio'       => Cambio::Manual,
        'combustivel'  => Combustivel::Flex,
    ]);
    $vehicle->user_id = $user->id;
    $vehicle->save();

    $user->delete();

    expect(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeFalse();
});
