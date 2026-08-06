<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Persists a vehicle owned by the given user. `Vehicle` has no factory yet
 * (none was needed before this policy), so this mirrors the manual
 * construction already used in tests/Feature/Models/VehicleTest.php.
 */
function vehicleOwnedBy(User $user): Vehicle
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

it('allows any authenticated user to view any vehicles', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Vehicle::class))->toBeTrue();
});

it('allows any authenticated user to view a vehicle they do not own', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($stranger->can('view', $vehicle))->toBeTrue();
});

it('allows the owner to update their own vehicle', function () {
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($owner->can('update', $vehicle))->toBeTrue();
});

it('denies a non-owner, non-admin user from updating a vehicle', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($stranger->can('update', $vehicle))->toBeFalse();
});

it('allows an admin to update a vehicle they do not own', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($admin->can('update', $vehicle))->toBeTrue();
});

it('allows the owner to delete their own vehicle', function () {
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($owner->can('delete', $vehicle))->toBeTrue();
});

it('denies a non-owner, non-admin user from deleting a vehicle', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($stranger->can('delete', $vehicle))->toBeFalse();
});

it('allows an admin to delete a vehicle they do not own', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedBy($owner);

    expect($admin->can('delete', $vehicle))->toBeTrue();
});

it('throws an AuthorizationException when Gate::authorize denies an update', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    $this->actingAs($stranger);

    expect(fn () => Gate::authorize('update', $vehicle))->toThrow(AuthorizationException::class);
});

it('renders a problem+json 403 when a denied vehicle authorization is exercised over HTTP', function () {
    // No controller exists yet for vehicles (that's #19); this inline route
    // mirrors the pattern in tests/Feature/Exceptions/ProblemJsonRendererTest.php
    // to prove the full HTTP-level path: policy denial -> AuthorizationException
    // -> the existing renderer -> problem+json 403. Uses `Gate::authorize`
    // rather than `$this->authorize` (the `AuthorizesRequests` controller
    // trait) since this is a plain route closure, not a controller action.
    Route::get('/__test/vehicles/{vehicle}/authorize-update', function (Vehicle $vehicle) {
        Gate::authorize('update', $vehicle);

        return response()->noContent();
    })->middleware('auth:sanctum');

    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedBy($owner);

    $response = $this->withHeader('Origin', 'http://localhost')
        ->actingAs($stranger)
        ->getJson("/__test/vehicles/{$vehicle->id}/authorize-update");

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
});
