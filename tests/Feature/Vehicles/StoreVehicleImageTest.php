<?php

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Persists a vehicle owned by the given user. `Vehicle` has no factory yet,
 * so this mirrors the manual construction already used in
 * tests/Feature/Vehicles/DeleteVehicleTest.php and friends.
 */
function vehicleOwnedByForImages(User $user): Vehicle
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

it('uploads multiple images and returns 201 with the created image records', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('back.jpg'),
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonStructure([
        'data' => [
            ['id', 'path', 'url', 'is_cover', 'created_at', 'updated_at'],
        ],
    ]);

    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->count())->toBe(2);
});

it('makes the first image ever uploaded for a vehicle its cover, and no other in the same batch', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ],
    ]);

    $response->assertCreated();

    $images = VehicleImage::query()->where('vehicle_id', $vehicle->id)->orderBy('id')->get();

    expect($images)->toHaveCount(3);
    expect($images[0]->is_cover)->toBeTrue();
    expect($images[1]->is_cover)->toBeFalse();
    expect($images[2]->is_cover)->toBeFalse();
});

it('never assigns a cover to images uploaded after the vehicle already has one', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->image('first.jpg')],
    ]);

    $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ],
    ]);

    $images = VehicleImage::query()->where('vehicle_id', $vehicle->id)->orderBy('id')->get();

    expect($images)->toHaveCount(3);
    expect($images[0]->is_cover)->toBeTrue();
    expect($images[1]->is_cover)->toBeFalse();
    expect($images[2]->is_cover)->toBeFalse();
});

it('stores the uploaded files physically on the public disk under the vehicle folder', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->image('front.jpg')],
    ]);

    $image = VehicleImage::query()->where('vehicle_id', $vehicle->id)->firstOrFail();

    expect($image->path)->toStartWith("vehicles/{$vehicle->id}/");
    Storage::disk('public')->assertExists($image->path);
});

it('returns a problem+json 422 when a file is not an image', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['files.0']);
    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->count())->toBe(0);
});

it('returns a problem+json 422 when a file exceeds 2MB', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg')],
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['files.0']);
    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->count())->toBe(0);
});

it('returns a problem+json 422 when files is missing', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", []);

    $response->assertProblemJson(status: 422, errorKeys: ['files']);
});

it('returns a problem+json 422 when files is an empty array', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [],
    ]);

    $response->assertProblemJson(status: 422, errorKeys: ['files']);
});

it('returns a problem+json 403 when a non-owner non-admin user tries to upload images', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($stranger)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->image('front.jpg')],
    ]);

    $response->assertProblemJson(status: 403, title: 'Forbidden', detail: 'This action is unauthorized.');
    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->count())->toBe(0);
});

it('allows an admin to upload images to a vehicle they do not own', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->actingAs($admin)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->image('front.jpg')],
    ]);

    $response->assertCreated();
});

it('returns a problem+json 404 for a vehicle that does not exist', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/vehicles/999999/images', [
        'files' => [UploadedFile::fake()->image('front.jpg')],
    ]);

    $response->assertProblemJson(status: 404);
});

it('returns a problem+json 401 for an unauthenticated request', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImages($owner);

    $response = $this->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->image('front.jpg')],
    ]);

    $response->assertProblemJson(status: 401, title: 'Unauthorized', detail: 'Unauthenticated.');
});
