<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

function makeVehicleForShow(User $owner, array $overrides = []): Vehicle
{
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
    $vehicle->user_id = $owner->id;

    return $vehicle;
}

it('returns the vehicle shaped by VehicleResource with creator and updater names', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();
    $editor = User::factory()->create();

    $this->actingAs($author);
    $vehicle = makeVehicleForShow($owner);
    $vehicle->save();

    $this->actingAs($editor);
    $vehicle->km = 15000;
    $vehicle->save();

    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->getJson("/api/vehicles/{$vehicle->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.placa', 'ABC1D23')
        ->assertJsonPath('data.chassi', '9BWZZZ377VT004251')
        ->assertJsonPath('data.marca', 'Volkswagen')
        ->assertJsonPath('data.modelo', 'Gol')
        ->assertJsonPath('data.versao', '1.0 MPI')
        ->assertJsonPath('data.cor', 'Prata')
        ->assertJsonPath('data.km', 15000)
        ->assertJsonPath('data.cambio', 'manual')
        ->assertJsonPath('data.combustivel', 'flex')
        ->assertJsonPath('data.user_id', $owner->id)
        ->assertJsonPath('data.creator.id', $author->id)
        ->assertJsonPath('data.creator.name', $author->name)
        ->assertJsonPath('data.updater.id', $editor->id)
        ->assertJsonPath('data.updater.name', $editor->name)
        ->assertJsonPath('data.images', []);
});

it('does not leak the full user model on the nested creator and updater', function () {
    $owner = User::factory()->create();
    $author = User::factory()->create();

    $this->actingAs($author);
    $vehicle = makeVehicleForShow($owner);
    $vehicle->save();

    $response = $this->actingAs($author)->getJson("/api/vehicles/{$vehicle->id}");

    $response->assertOk()
        ->assertJsonMissingPath('data.creator.email')
        ->assertJsonMissingPath('data.creator.password')
        ->assertJsonMissingPath('data.updater.email')
        ->assertJsonMissingPath('data.updater.password')
        ->assertJsonMissingPath('data.password');

    expect(array_keys($response->json('data.creator')))->toBe(['id', 'name'])
        ->and(array_keys($response->json('data.updater')))->toBe(['id', 'name']);
});

it('returns a problem+json 404 for a vehicle that does not exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/vehicles/999999');

    $response->assertProblemJson(status: 404);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForShow($owner);
    $vehicle->save();

    $response = $this->getJson("/api/vehicles/{$vehicle->id}");

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
