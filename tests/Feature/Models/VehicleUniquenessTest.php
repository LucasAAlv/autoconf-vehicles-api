<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;

it('rejects a duplicate placa at the database level', function () {
    $user = User::factory()->create();

    $first = new Vehicle([
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
    $first->user_id = $user->id;
    $first->save();

    $second = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => '1HGCM82633A004352',
        'marca'        => 'Fiat',
        'modelo'       => 'Argo',
        'versao'       => '1.3',
        'valor_venda'  => '60000.00',
        'cor'          => 'Branco',
        'km'           => 500,
        'cambio'       => Cambio::Automatico,
        'combustivel'  => Combustivel::Gasolina,
    ]);
    $second->user_id = $user->id;
    $second->save();
})->throws(QueryException::class);

it('rejects a duplicate chassi at the database level', function () {
    $user = User::factory()->create();

    $first = new Vehicle([
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
    $first->user_id = $user->id;
    $first->save();

    $second = new Vehicle([
        'placa'        => 'XYZ9A87',
        'chassi'       => '9BWZZZ377VT004251',
        'marca'        => 'Fiat',
        'modelo'       => 'Argo',
        'versao'       => '1.3',
        'valor_venda'  => '60000.00',
        'cor'          => 'Branco',
        'km'           => 500,
        'cambio'       => Cambio::Automatico,
        'combustivel'  => Combustivel::Gasolina,
    ]);
    $second->user_id = $user->id;
    $second->save();
})->throws(QueryException::class);

it('rejects a chassi shorter than 17 characters at the database level', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => 'SHORTCHASSI16CH',
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
})->throws(QueryException::class);

it('rejects a chassi longer than 17 characters at the database level', function () {
    $user = User::factory()->create();

    $vehicle = new Vehicle([
        'placa'        => 'ABC1D23',
        'chassi'       => str_repeat('A', 18),
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
})->throws(QueryException::class);
