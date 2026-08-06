<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Persists a vehicle owned by the given user. `Vehicle` has no factory yet,
 * so this mirrors the manual construction already used in
 * tests/Feature/Policies/VehiclePolicyTest.php and tests/Feature/Vehicles/ShowVehicleTest.php.
 */
function vehicleOwnedByForDelete(User $user): Vehicle
{
    $vehicle = new Vehicle([
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
    ]);
    $vehicle->user_id = $user->id;
    $vehicle->save();

    return $vehicle;
}

it('allows the owner to delete their own vehicle and returns 204', function () {
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}");

    $response->assertNoContent();
});

it('removes the vehicle row from the database after the owner deletes it', function () {
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}");

    $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
});

it('allows an admin to delete a vehicle they do not own', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $response = $this->actingAs($admin)->deleteJson("/api/vehicles/{$vehicle->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
});

it('returns a problem+json 403 when a non-owner non-admin user tries to delete a vehicle', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $response = $this->actingAs($stranger)->deleteJson("/api/vehicles/{$vehicle->id}");

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
});

it('keeps the vehicle row when a non-owner non-admin user tries to delete it', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $this->actingAs($stranger)->deleteJson("/api/vehicles/{$vehicle->id}");

    $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
});

it('returns a problem+json 404 for a vehicle that does not exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson('/api/vehicles/999999');

    $response->assertProblemJson(status: 404);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForDelete($owner);

    $response = $this->deleteJson("/api/vehicles/{$vehicle->id}");

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
