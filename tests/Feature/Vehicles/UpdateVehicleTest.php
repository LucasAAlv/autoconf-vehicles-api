<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists a vehicle owned by the given user. Mirrors the manual
 * construction already used by StoreVehicleTest/ShowVehicleTest — `Vehicle`
 * still has no factory.
 */
function makeVehicleForUpdate(User $owner, array $overrides = []): Vehicle
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
    $vehicle->save();

    return $vehicle;
}

it('allows the owner to fully update their vehicle via PUT', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($owner)->putJson("/api/vehicles/{$vehicle->id}", [
        'placa'       => 'XYZ9A87',
        'chassi'      => '1HGCM82633A004352',
        'marca'       => 'Fiat',
        'modelo'      => 'Uno',
        'versao'      => '1.4 Way',
        'valor_venda' => '32000.00',
        'cor'         => 'Branco',
        'km'          => 20000,
        'cambio'      => Cambio::Automatico->value,
        'combustivel' => Combustivel::Gasolina->value,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.placa', 'XYZ9A87')
        ->assertJsonPath('data.chassi', '1HGCM82633A004352')
        ->assertJsonPath('data.marca', 'Fiat')
        ->assertJsonPath('data.km', 20000)
        ->assertJsonPath('data.cambio', 'automatico')
        ->assertJsonPath('data.combustivel', 'gasolina');

    expect($vehicle->fresh()->placa)->toBe('XYZ9A87');
});

it('allows the owner to partially update their vehicle via PATCH, leaving absent fields untouched', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => 99000,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.km', 99000)
        ->assertJsonPath('data.placa', 'ABC1D23')
        ->assertJsonPath('data.chassi', '9BWZZZ377VT004251')
        ->assertJsonPath('data.marca', 'Volkswagen');

    $vehicle->refresh();
    expect($vehicle->km)->toBe(99000)
        ->and($vehicle->placa)->toBe('ABC1D23')
        ->and($vehicle->marca)->toBe('Volkswagen');
});

it('allows the owner to partially update their vehicle via PUT with a partial payload too', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($owner)->putJson("/api/vehicles/{$vehicle->id}", [
        'cor' => 'Preto',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.cor', 'Preto')
        ->assertJsonPath('data.placa', 'ABC1D23');

    expect($vehicle->fresh()->cor)->toBe('Preto');
});

it('stamps updated_by with the authenticated editor via the observer', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patchJson("/api/vehicles/{$vehicle->id}", ['km' => 5000])
        ->assertOk()
        ->assertJsonPath('data.updater.id', $admin->id)
        ->assertJsonPath('data.updater.name', $admin->name);

    expect($vehicle->fresh()->updated_by)->toBe($admin->id);
});

it('allows an admin to update a vehicle they do not own', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($admin)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => 1000,
    ]);

    $response->assertOk()->assertJsonPath('data.km', 1000);
    expect($vehicle->fresh()->km)->toBe(1000);
});

it('returns a problem+json 403 when a non-owner, non-admin user updates a vehicle', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($stranger)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => 1000,
    ]);

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
    expect($vehicle->fresh()->km)->toBe(12000);
});

it('ignores the current record for uniqueness but still rejects a placa used by another vehicle', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);
    $other = makeVehicleForUpdate($owner, ['placa' => 'XYZ9A87', 'chassi' => '1HGCM82633A004352']);

    $sameValue = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'placa' => $vehicle->placa,
    ]);
    $sameValue->assertOk();

    $conflict = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'placa' => $other->placa,
    ]);
    $conflict->assertProblemJson(status: 422, errorKeys: ['placa']);
});

it('ignores the current record for uniqueness but still rejects a chassi used by another vehicle', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);
    $other = makeVehicleForUpdate($owner, ['placa' => 'XYZ9A87', 'chassi' => '1HGCM82633A004352']);

    $sameValue = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'chassi' => $vehicle->chassi,
    ]);
    $sameValue->assertOk();

    $conflict = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'chassi' => $other->chassi,
    ]);
    $conflict->assertProblemJson(status: 422, errorKeys: ['chassi']);
});

it('returns a problem+json 422 when a present field fails validation', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => -5,
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['km']);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    $owner = User::factory()->create();
    $vehicle = makeVehicleForUpdate($owner);

    $response = $this->patchJson("/api/vehicles/{$vehicle->id}", ['km' => 1000]);

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
