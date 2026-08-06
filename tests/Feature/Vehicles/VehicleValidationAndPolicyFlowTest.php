<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Mirrors the payload shape used by StoreVehicleTest — `Vehicle` still has no
 * factory, so requests are built by hand the same way.
 */
function validFlowPayload(array $overrides = []): array
{
    return array_merge([
        'placa'       => 'ABC1D23',
        'chassi'      => '9BWZZZ377VT004251',
        'marca'       => 'Volkswagen',
        'modelo'      => 'Gol',
        'versao'      => '1.0 MPI',
        'valor_venda' => '45000.99',
        'cor'         => 'Prata',
        'km'          => 12000,
        'cambio'      => Cambio::Manual->value,
        'combustivel' => Combustivel::Flex->value,
    ], $overrides);
}

it('walks through validation and ownership rules for a single vehicle end to end', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $admin = User::factory()->admin()->create();

    // An invalid `placa` format is rejected before anything is persisted.
    $this->actingAs($owner)->postJson('/api/vehicles', validFlowPayload([
        'placa' => '1234567',
    ]))->assertProblemJson(status: 422, errorKeys: ['placa']);

    $this->assertDatabaseMissing('vehicles', ['chassi' => '9BWZZZ377VT004251']);

    // A valid Mercosul-format `placa` is accepted and the vehicle is created.
    $createResponse = $this->actingAs($owner)->postJson('/api/vehicles', validFlowPayload());

    $createResponse->assertCreated();
    $vehicle = Vehicle::query()->where('placa', 'ABC1D23')->firstOrFail();

    // A second vehicle reusing the same `placa` (different `chassi`) is
    // rejected as a duplicate.
    $this->actingAs($owner)->postJson('/api/vehicles', validFlowPayload([
        'placa'  => 'ABC1D23',
        'chassi' => '1HGCM82633A004352',
    ]))->assertProblemJson(status: 422, errorKeys: ['placa']);

    // A second vehicle reusing the same `chassi` (different `placa`) is also
    // rejected as a duplicate.
    $this->actingAs($owner)->postJson('/api/vehicles', validFlowPayload([
        'placa'  => 'XYZ9A87',
        'chassi' => '9BWZZZ377VT004251',
    ]))->assertProblemJson(status: 422, errorKeys: ['chassi']);

    expect(Vehicle::query()->count())->toBe(1);

    // A non-owner, non-admin user cannot update the vehicle.
    $this->actingAs($stranger)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => 1000,
    ])->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');

    // ...nor delete it.
    $this->actingAs($stranger)->deleteJson("/api/vehicles/{$vehicle->id}")
        ->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');

    $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'km' => 12000]);

    // An admin, who owns neither vehicle, may update it.
    $updateResponse = $this->actingAs($admin)->patchJson("/api/vehicles/{$vehicle->id}", [
        'km' => 99000,
    ]);

    $updateResponse->assertOk()->assertJsonPath('data.km', 99000);

    // The update actually took effect.
    expect($vehicle->fresh()->km)->toBe(99000);
});
