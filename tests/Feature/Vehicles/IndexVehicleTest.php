<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists `$count` vehicles owned by the given user, each with a distinct
 * `placa`/`chassi` (both unique columns). `Vehicle` has no factory yet, so
 * this mirrors the manual construction already used across
 * tests/Feature/Vehicles/*.php, but derives the unique fields from an index
 * so many vehicles can be created in a single call.
 *
 * @return list<Vehicle>
 */
function makeVehiclesForIndex(User $owner, int $count): array
{
    $vehicles = [];

    for ($i = 0; $i < $count; $i++) {
        $digit = $i % 10;
        $letter = chr(65 + (intdiv($i, 10) % 26));
        $suffix = str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT);

        $vehicle = new Vehicle([
            'placa'       => sprintf('ABC%d%s%s', $digit, $letter, $suffix),
            'chassi'      => sprintf('9BWZZZ377VT%06d', $i),
            'marca'       => 'Volkswagen',
            'modelo'      => 'Gol',
            'versao'      => '1.0 MPI',
            'valor_venda' => '45000.99',
            'cor'         => 'Prata',
            'km'          => 12000,
            'cambio'      => Cambio::Manual,
            'combustivel' => Combustivel::Flex,
        ]);
        $vehicle->user_id = $owner->id;
        $vehicle->save();

        $vehicles[] = $vehicle;
    }

    return $vehicles;
}

it('returns the first page of vehicles with a sane default per_page', function () {
    $user = User::factory()->create();
    makeVehiclesForIndex($user, 3);

    $response = $this->actingAs($user)->getJson('/api/vehicles');

    $response->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(3, 'data');
});

it('returns the next slice of vehicles when page=2 is requested', function () {
    $user = User::factory()->create();
    makeVehiclesForIndex($user, 5);

    $firstPage = $this->actingAs($user)->getJson('/api/vehicles?per_page=2&page=1');
    $secondPage = $this->actingAs($user)->getJson('/api/vehicles?per_page=2&page=2');

    $firstPage->assertOk()->assertJsonCount(2, 'data');
    $secondPage->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonCount(2, 'data');

    expect($firstPage->json('data.0.id'))->not->toBe($secondPage->json('data.0.id'));
});

it('honours per_page up to the cap', function () {
    $user = User::factory()->create();
    makeVehiclesForIndex($user, 3);

    $response = $this->actingAs($user)->getJson('/api/vehicles?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');
});

it('clamps a per_page above the cap instead of erroring or returning everything', function () {
    $user = User::factory()->create();
    makeVehiclesForIndex($user, 3);

    $response = $this->actingAs($user)->getJson('/api/vehicles?per_page=500');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonCount(3, 'data');
});

it('returns an empty list with correct metadata when there are no vehicles', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/vehicles');

    $response->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(0, 'data');
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    $response = $this->getJson('/api/vehicles');

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
