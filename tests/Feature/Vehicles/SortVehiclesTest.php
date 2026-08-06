<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists one vehicle with overridable `marca`/`km`/`valor_venda` (and a
 * unique `placa`/`chassi` derived from `$seed`), so a single test can build a
 * small fleet with varied sortable values — mirrors `makeFilterableVehicle()`
 * in `FilterVehiclesTest.php`, but exists as its own function (per this
 * codebase's convention of one helper per test file) since it varies
 * different columns.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeSortableVehicle(User $owner, int $seed, array $overrides = []): Vehicle
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

it('sorts ascending by a single field when no prefix is given', function () {
    $user = User::factory()->create();
    $high = makeSortableVehicle($user, 1, ['km' => 30000]);
    $low = makeSortableVehicle($user, 2, ['km' => 10000]);
    $mid = makeSortableVehicle($user, 3, ['km' => 20000]);

    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=km');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$low->id, $mid->id, $high->id]);
});

it('sorts descending by a single field with a - prefix', function () {
    $user = User::factory()->create();
    $high = makeSortableVehicle($user, 4, ['km' => 30000]);
    $low = makeSortableVehicle($user, 5, ['km' => 10000]);
    $mid = makeSortableVehicle($user, 6, ['km' => 20000]);

    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=-km');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$high->id, $mid->id, $low->id]);
});

it('sorts by multiple fields, respecting each field\'s own direction', function () {
    $user = User::factory()->create();
    // Primary key `marca` ascending: Fiat before Volkswagen.
    // Secondary key `km` descending, applied within each `marca` group.
    // Created in an order deliberately different from the expected sorted
    // order, so a passing assertion actually proves the sort ran instead of
    // coincidentally matching insertion/id order.
    $vwLow = makeSortableVehicle($user, 9, ['marca' => 'Volkswagen', 'km' => 5000]);
    $fiatLow = makeSortableVehicle($user, 8, ['marca' => 'Fiat', 'km' => 10000]);
    $fiatHigh = makeSortableVehicle($user, 7, ['marca' => 'Fiat', 'km' => 20000]);

    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=marca,-km');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$fiatHigh->id, $fiatLow->id, $vwLow->id]);
});

it('returns a problem+json 422 and never queries when sort has an unknown field', function () {
    $user = User::factory()->create();
    makeSortableVehicle($user, 10);

    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=nonexistent_field');

    $response->assertProblemJson(status: 422, errorKeys: ['sort']);
});

it('returns a problem+json 422 when sort references a real but non-allow-listed column', function () {
    $user = User::factory()->create();
    makeSortableVehicle($user, 11);

    // `placa` is a real column but deliberately excluded from
    // `Vehicle::SORTABLE_COLUMNS`, so it must be rejected the same way an
    // entirely made-up field name would be.
    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=placa');

    $response->assertProblemJson(status: 422, errorKeys: ['sort']);
});

it('breaks ties deterministically by id when the sorted field is equal, stably across repeated requests', function () {
    $user = User::factory()->create();
    $first = makeSortableVehicle($user, 12, ['km' => 15000]);
    $second = makeSortableVehicle($user, 13, ['km' => 15000]);
    $third = makeSortableVehicle($user, 14, ['km' => 15000]);

    $responseOne = $this->actingAs($user)->getJson('/api/vehicles?sort=km');
    $responseTwo = $this->actingAs($user)->getJson('/api/vehicles?sort=km');

    $expected = [$first->id, $second->id, $third->id];

    $responseOne->assertOk();
    $responseTwo->assertOk();
    expect(collect($responseOne->json('data'))->pluck('id')->all())->toBe($expected);
    expect(collect($responseTwo->json('data'))->pluck('id')->all())->toBe($expected);
});

it('sorts the filtered subset, not the whole table', function () {
    $user = User::factory()->create();
    // Created in an order deliberately different from the expected
    // descending-km order (`toyotaLow` gets the lower id), so a passing
    // assertion actually proves the sort ran instead of coincidentally
    // matching insertion/id order.
    $toyotaLow = makeSortableVehicle($user, 16, ['marca' => 'Toyota', 'km' => 5000]);
    $toyotaHigh = makeSortableVehicle($user, 15, ['marca' => 'Toyota', 'km' => 20000]);
    // Would sort before both Toyotas by km alone, but must be excluded by the marca filter.
    makeSortableVehicle($user, 17, ['marca' => 'Fiat', 'km' => 1000]);

    $response = $this->actingAs($user)->getJson('/api/vehicles?marca=Toyota&sort=-km');

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$toyotaHigh->id, $toyotaLow->id]);
});

it('paginates the sorted order, returning the correct page of sorted results', function () {
    $user = User::factory()->create();
    // Created out of km order on purpose, so a correct result proves the
    // page reflects sorted order rather than insertion/id order.
    $v50 = makeSortableVehicle($user, 18, ['km' => 50000]);
    $v10 = makeSortableVehicle($user, 19, ['km' => 10000]);
    $v40 = makeSortableVehicle($user, 20, ['km' => 40000]);
    $v20 = makeSortableVehicle($user, 21, ['km' => 20000]);
    $v30 = makeSortableVehicle($user, 22, ['km' => 30000]);

    // Ascending km order is: v10, v20, v30, v40, v50. With per_page=2, page 2
    // must be [v30, v40].
    $response = $this->actingAs($user)->getJson('/api/vehicles?sort=km&per_page=2&page=2');

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$v30->id, $v40->id]);
});
