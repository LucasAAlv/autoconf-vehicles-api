<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists one vehicle with overridable `marca`/`km`/`valor_venda` (and a
 * unique `placa`/`chassi` derived from `$seed`), so a single test can build a
 * small fleet that is deliberately varied across all three axes exercised by
 * this file — filtering, multi-field sorting and pagination — at once.
 * Mirrors `makeFilterableVehicle()`/`makeSortableVehicle()` in
 * `FilterVehiclesTest.php`/`SortVehiclesTest.php`, kept as its own copy per
 * this codebase's one-helper-per-test-file convention.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeFlowVehicle(User $owner, int $seed, array $overrides = []): Vehicle
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

it('combines a filter with multi-field sorting and pagination in a single request', function () {
    $user = User::factory()->create();

    // Volkswagen fleet, deliberately not created in sorted order. Two share
    // km=10000 so the secondary `-valor_venda` key is actually exercised.
    $vwHighValorAtLowKm = makeFlowVehicle($user, 1, ['marca' => 'Volkswagen', 'km' => 10000, 'valor_venda' => '70000.00']);
    $vwLowValorAtLowKm = makeFlowVehicle($user, 2, ['marca' => 'Volkswagen', 'km' => 10000, 'valor_venda' => '50000.00']);
    $vwMidKm = makeFlowVehicle($user, 3, ['marca' => 'Volkswagen', 'km' => 20000, 'valor_venda' => '60000.00']);
    $vwHighKm = makeFlowVehicle($user, 4, ['marca' => 'Volkswagen', 'km' => 30000, 'valor_venda' => '40000.00']);

    // Would sort before every Volkswagen by km alone, but must be excluded by
    // the `marca` filter.
    makeFlowVehicle($user, 5, ['marca' => 'Fiat', 'km' => 1000, 'valor_venda' => '30000.00']);

    // Ascending km, descending valor_venda within ties, gives:
    // vwHighValorAtLowKm, vwLowValorAtLowKm, vwMidKm, vwHighKm.
    // With per_page=2, page 1 must be the first two of that order.
    $response = $this->actingAs($user)->getJson(
        '/api/vehicles?marca=Volkswagen&sort=km,-valor_venda&per_page=2&page=1'
    );

    $response->assertOk()
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$vwHighValorAtLowKm->id, $vwLowValorAtLowKm->id]);
});

it('short-circuits with a problem+json 422 for an unknown sort field even with filters and pagination present', function () {
    $user = User::factory()->create();
    makeFlowVehicle($user, 6, ['marca' => 'Volkswagen']);

    $response = $this->actingAs($user)->getJson(
        '/api/vehicles?marca=Volkswagen&sort=nonexistent_field&per_page=2&page=1'
    );

    $response->assertProblemJson(status: 422, errorKeys: ['sort']);
});

it('reports pagination metadata for the filtered count, not the full table', function () {
    $user = User::factory()->create();

    for ($i = 7; $i < 12; $i++) {
        makeFlowVehicle($user, $i, ['marca' => 'Toyota', 'modelo' => 'Corolla']);
    }

    for ($i = 12; $i < 15; $i++) {
        makeFlowVehicle($user, $i, ['marca' => 'Fiat', 'modelo' => 'Argo']);
    }

    // 5 Toyota + 3 Fiat = 8 vehicles total, but the filter narrows to 5.
    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Toyota&per_page=2&page=1');

    $response->assertOk()
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');
});
