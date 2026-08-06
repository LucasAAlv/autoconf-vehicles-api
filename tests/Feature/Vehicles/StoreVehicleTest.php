<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;

function validVehiclePayload(array $overrides = []): array
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

it('creates a vehicle owned by the authenticated user and returns 201', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/vehicles', validVehiclePayload());

    $response->assertCreated();

    $vehicle = Vehicle::query()->where('placa', 'ABC1D23')->firstOrFail();

    expect($vehicle->user_id)->toBe($user->id);

    $response->assertJsonPath('data.id', $vehicle->id)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.placa', 'ABC1D23')
        ->assertJsonPath('data.chassi', '9BWZZZ377VT004251');
});

it('ignores a client-supplied user_id and assigns the authenticated user instead', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/vehicles', validVehiclePayload([
        'user_id' => $otherUser->id,
    ]));

    $response->assertCreated();

    $vehicle = Vehicle::query()->where('placa', 'ABC1D23')->firstOrFail();

    expect($vehicle->user_id)->toBe($user->id)
        ->and($vehicle->user_id)->not->toBe($otherUser->id);
});

it('stamps created_by and updated_by with the authenticated user via the observer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/vehicles', validVehiclePayload());

    $vehicle = Vehicle::query()->where('placa', 'ABC1D23')->firstOrFail();

    expect($vehicle->created_by)->toBe($user->id)
        ->and($vehicle->updated_by)->toBe($user->id);
});

it('returns a problem+json 422 when required fields are missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/vehicles', []);

    $response->assertProblemJson(status: 422, errorKeys: [
        'placa', 'chassi', 'marca', 'modelo', 'versao', 'valor_venda', 'cor', 'km', 'cambio', 'combustivel',
    ]);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    $response = $this->postJson('/api/vehicles', validVehiclePayload());

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
