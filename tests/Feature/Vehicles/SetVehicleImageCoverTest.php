<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Support\Facades\Storage;

/**
 * Persists a vehicle owned by the given user. `Vehicle` has no factory yet,
 * so this mirrors the manual construction already used in
 * tests/Feature/Vehicles/StoreVehicleImageTest.php and friends.
 */
function vehicleOwnedByForCover(User $user, string $placa = 'ABC1D23', string $chassi = '9BWZZZ377VT004251'): Vehicle
{
    $vehicle = new Vehicle([
        'placa'       => $placa,
        'chassi'      => $chassi,
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

it('swaps the cover from image A to image B', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);
    $imageB = $vehicle->images()->create(['path' => 'b.jpg', 'is_cover' => false]);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageB->id}/cover");

    $response->assertOk();
    $response->assertJsonPath('data.id', $imageB->id);
    $response->assertJsonPath('data.is_cover', true);

    expect($imageA->refresh()->is_cover)->toBeFalse();
    expect($imageB->refresh()->is_cover)->toBeTrue();
});

it('sets the cover when the vehicle currently has none', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => false]);
    $imageB = $vehicle->images()->create(['path' => 'b.jpg', 'is_cover' => false]);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageB->id}/cover");

    $response->assertOk();
    expect($imageA->refresh()->is_cover)->toBeFalse();
    expect($imageB->refresh()->is_cover)->toBeTrue();
});

it('is a safe no-op when the target image is already the cover', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageA->id}/cover");

    $response->assertOk();
    $response->assertJsonPath('data.id', $imageA->id);
    $response->assertJsonPath('data.is_cover', true);
    expect($imageA->refresh()->is_cover)->toBeTrue();
    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->where('is_cover', true)->count())->toBe(1);
});

it('returns a problem+json 404 when the image belongs to a different vehicle', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);
    $otherVehicle = vehicleOwnedByForCover($owner, 'XYZ9W87', '9BWZZZ377VT004252');

    $otherImage = $otherVehicle->images()->create(['path' => 'other.jpg', 'is_cover' => true]);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/{$otherImage->id}/cover");

    $response->assertProblemJson(status: 404);
    expect($otherImage->refresh()->is_cover)->toBeTrue();
});

it('returns a problem+json 404 for a nonexistent image id', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/999999/cover");

    $response->assertProblemJson(status: 404);
});

it('returns a problem+json 403 when a non-owner non-admin user tries to change the cover', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);
    $imageB = $vehicle->images()->create(['path' => 'b.jpg', 'is_cover' => false]);

    $response = $this->actingAs($stranger)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageB->id}/cover");

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
    expect($imageA->refresh()->is_cover)->toBeTrue();
    expect($imageB->refresh()->is_cover)->toBeFalse();
});

it('allows an admin to change the cover of a vehicle they do not own', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);
    $imageB = $vehicle->images()->create(['path' => 'b.jpg', 'is_cover' => false]);

    $response = $this->actingAs($admin)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageB->id}/cover");

    $response->assertOk();
    expect($imageB->refresh()->is_cover)->toBeTrue();
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);

    $response = $this->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageA->id}/cover");

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});

it('returns the updated VehicleImageResource shape', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForCover($owner);

    $imageA = $vehicle->images()->create(['path' => 'a.jpg', 'is_cover' => true]);
    $imageB = $vehicle->images()->create(['path' => 'b.jpg', 'is_cover' => false]);

    $response = $this->actingAs($owner)->patchJson("/api/vehicles/{$vehicle->id}/images/{$imageB->id}/cover");

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => ['id', 'path', 'url', 'is_cover', 'created_at', 'updated_at'],
    ]);
});
