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
function vehicleOwnedByForImageDelete(User $user, ?string $placa = null, ?string $chassi = null): Vehicle
{
    $vehicle = new Vehicle([
        'placa'       => $placa ?? 'ABC1D23',
        'chassi'      => $chassi ?? '9BWZZZ377VT004251',
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

/**
 * Creates a `VehicleImage` row for the given vehicle with a real fake file
 * written to `Storage::fake('public')`, so tests can assert the physical
 * file is actually gone after deletion.
 */
function vehicleImageFor(Vehicle $vehicle, bool $isCover = false): VehicleImage
{
    $path = "vehicles/{$vehicle->id}/".uniqid('image_', true).'.jpg';
    Storage::disk('public')->put($path, 'fake-image-contents');

    return $vehicle->images()->create([
        'path'     => $path,
        'is_cover' => $isCover,
    ]);
}

it('deletes a non-cover image and removes the DB row and the physical file, leaving other images unaffected', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $cover = vehicleImageFor($vehicle, isCover: true);
    $other = vehicleImageFor($vehicle, isCover: false);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$other->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('vehicle_images', ['id' => $other->id]);
    Storage::disk('public')->assertMissing($other->path);

    $this->assertDatabaseHas('vehicle_images', ['id' => $cover->id, 'is_cover' => true]);
    Storage::disk('public')->assertExists($cover->path);
});

it('deletes the cover image and leaves the vehicle with zero cover images, without promoting another', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $cover = vehicleImageFor($vehicle, isCover: true);
    $other = vehicleImageFor($vehicle, isCover: false);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$cover->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('vehicle_images', ['id' => $cover->id]);
    Storage::disk('public')->assertMissing($cover->path);

    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->where('is_cover', true)->count())->toBe(0);
    $this->assertDatabaseHas('vehicle_images', ['id' => $other->id, 'is_cover' => false]);
});

it('returns a problem+json 404 when the image belongs to a different vehicle', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $otherVehicle = vehicleOwnedByForImageDelete($owner, placa: 'XYZ9K88', chassi: '9BWZZZ377VT004252');
    $imageOnOtherVehicle = vehicleImageFor($otherVehicle, isCover: true);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$imageOnOtherVehicle->id}");

    $response->assertProblemJson(status: 404);
    $this->assertDatabaseHas('vehicle_images', ['id' => $imageOnOtherVehicle->id]);
    Storage::disk('public')->assertExists($imageOnOtherVehicle->path);
});

it('returns a problem+json 404 for a nonexistent imageId', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}/images/999999");

    $response->assertProblemJson(status: 404);
});

it('returns a problem+json 403 when a non-owner non-admin user tries to delete an image', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $image = vehicleImageFor($vehicle, isCover: true);

    $response = $this->actingAs($stranger)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}");

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
    $this->assertDatabaseHas('vehicle_images', ['id' => $image->id]);
    Storage::disk('public')->assertExists($image->path);
});

it('allows an admin to delete an image from a vehicle they do not own', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $image = vehicleImageFor($vehicle, isCover: true);

    $response = $this->actingAs($admin)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('vehicle_images', ['id' => $image->id]);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $image = vehicleImageFor($vehicle, isCover: true);

    $response = $this->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}");

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});

it('returns 204 with an empty body on success', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageDelete($owner);
    $image = vehicleImageFor($vehicle, isCover: false);

    $response = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}/images/{$image->id}");

    $response->assertNoContent();
    expect($response->getContent())->toBe('');
});
