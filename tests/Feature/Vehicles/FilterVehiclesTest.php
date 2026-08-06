<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists one vehicle with deliberately overridable `placa`/`marca`/`modelo`
 * (and a unique `chassi` derived from `$seed`), so a single test can build a
 * small fleet with varied values to exercise `q`/`marca`/`modelo`/`placa`
 * filtering — unlike `makeVehiclesForIndex()` in `IndexVehicleTest.php`,
 * which stamps every vehicle with the same `marca`/`modelo` because
 * pagination doesn't care about column values.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeFilterableVehicle(User $owner, int $seed, array $overrides = []): Vehicle
{
    $vehicle = new Vehicle(array_merge([
        'placa'       => sprintf('ABC%dD%02d', $seed % 10, $seed % 100),
        'chassi'      => sprintf('9BWZZZ377VT%06d', $seed),
        'marca'       => 'Volkswagen',
        'modelo'      => 'Gol',
        'versao'      => '1.0 MPI',
        'valor_venda' => '45000.99',
        'cor'         => 'Prata',
        'km'          => 12000,
        'cambio'      => Cambio::Manual,
        'combustivel' => Combustivel::Flex,
    ], $overrides));
    $vehicle->user_id = $owner->id;
    $vehicle->save();

    return $vehicle;
}

it('filters by marca', function () {
    $user = User::factory()->create();
    $vw = makeFilterableVehicle($user, 1, ['placa' => 'ABC1D01', 'marca' => 'Volkswagen', 'modelo' => 'Gol']);
    makeFilterableVehicle($user, 2, ['placa' => 'ABC2D02', 'marca' => 'Fiat', 'modelo' => 'Argo']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Volkswagen');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vw->id);
});

it('filters by modelo', function () {
    $user = User::factory()->create();
    $polo = makeFilterableVehicle($user, 3, ['placa' => 'ABC3D03', 'marca' => 'Volkswagen', 'modelo' => 'Polo']);
    makeFilterableVehicle($user, 4, ['placa' => 'ABC4D04', 'marca' => 'Volkswagen', 'modelo' => 'Gol']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?modelo=Polo');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $polo->id);
});

it('filters by placa', function () {
    $user = User::factory()->create();
    $target = makeFilterableVehicle($user, 5, ['placa' => 'XYZ5E05']);
    makeFilterableVehicle($user, 6, ['placa' => 'ABC6D06']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?placa=XYZ5E05');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('matches q against placa', function () {
    $user = User::factory()->create();
    $target = makeFilterableVehicle($user, 7, ['placa' => 'QWE7R07', 'marca' => 'Renault', 'modelo' => 'Kwid']);
    makeFilterableVehicle($user, 8, ['placa' => 'ABC8D08', 'marca' => 'Fiat', 'modelo' => 'Argo']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?q=QWE7R07');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('matches q against marca', function () {
    $user = User::factory()->create();
    $target = makeFilterableVehicle($user, 9, ['placa' => 'ABC9D09', 'marca' => 'Chevrolet', 'modelo' => 'Onix']);
    makeFilterableVehicle($user, 10, ['placa' => 'ABC0D10', 'marca' => 'Fiat', 'modelo' => 'Mobi']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?q=Chevrolet');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('matches q against modelo', function () {
    $user = User::factory()->create();
    $target = makeFilterableVehicle($user, 11, ['placa' => 'ABC1D11', 'marca' => 'Fiat', 'modelo' => 'Toro']);
    makeFilterableVehicle($user, 12, ['placa' => 'ABC2D12', 'marca' => 'Fiat', 'modelo' => 'Argo']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?q=Toro');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('combines q and marca to narrow to the intersection, not the union', function () {
    $user = User::factory()->create();
    $gol = makeFilterableVehicle($user, 13, ['placa' => 'ABC3D13', 'marca' => 'Volkswagen', 'modelo' => 'Gol']);
    // Same marca, different modelo: matches `marca=Volkswagen` alone but not `q=Gol`.
    makeFilterableVehicle($user, 14, ['placa' => 'ABC4D14', 'marca' => 'Volkswagen', 'modelo' => 'Polo']);
    // Same modelo term, different marca: matches `q=Gol` alone but not `marca=Volkswagen`.
    makeFilterableVehicle($user, 15, ['placa' => 'ABC5D15', 'marca' => 'Fiat', 'modelo' => 'Gol Golzao']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?q=Gol&marca=Volkswagen');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $gol->id);
});

it('combines marca and modelo to narrow to the intersection, not the union', function () {
    $user = User::factory()->create();
    $argo = makeFilterableVehicle($user, 16, ['placa' => 'ABC6D16', 'marca' => 'Fiat', 'modelo' => 'Argo']);
    makeFilterableVehicle($user, 17, ['placa' => 'ABC7D17', 'marca' => 'Fiat', 'modelo' => 'Mobi']);
    makeFilterableVehicle($user, 18, ['placa' => 'ABC8D18', 'marca' => 'Volkswagen', 'modelo' => 'Argo Fake']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Fiat&modelo=Argo');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $argo->id);
});

it('matches marca case-insensitively', function () {
    $user = User::factory()->create();
    $target = makeFilterableVehicle($user, 19, ['placa' => 'ABC9D19', 'marca' => 'Volkswagen', 'modelo' => 'Gol']);
    makeFilterableVehicle($user, 20, ['placa' => 'ABC0D20', 'marca' => 'Fiat', 'modelo' => 'Argo']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=vOLKSWAGEN');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id);
});

it('returns an empty list with correct pagination metadata when no filter matches', function () {
    $user = User::factory()->create();
    makeFilterableVehicle($user, 21, ['placa' => 'ABC1D21', 'marca' => 'Volkswagen', 'modelo' => 'Gol']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Nonexistent');

    $response->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonCount(0, 'data');
});

it('respects per_page on filtered results', function () {
    $user = User::factory()->create();
    for ($i = 22; $i < 26; $i++) {
        makeFilterableVehicle($user, $i, [
            'placa'  => sprintf('ABC%dD%02d', $i % 10, $i % 100),
            'marca'  => 'Toyota',
            'modelo' => 'Corolla',
        ]);
    }
    makeFilterableVehicle($user, 26, ['placa' => 'ABC6D26', 'marca' => 'Fiat', 'modelo' => 'Argo']);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Toyota&per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(2, 'data');
});
