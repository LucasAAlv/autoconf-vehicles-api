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
 * tests/Feature/Vehicles/StoreVehicleImageTest.php and friends.
 */
function vehicleOwnedByForImageFlow(User $user): Vehicle
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

/**
 * End-to-end flow covering the four acceptance-criteria bullets of issue #28
 * in a single pass: batch upload, rejection of oversized/non-image files,
 * the single-cover invariant on swap, and physical-file cleanup on vehicle
 * deletion. Each concern already has dedicated coverage elsewhere
 * (StoreVehicleImageTest, SetVehicleImageCoverTest, DeleteVehicleImageTest),
 * so this test does not repeat their edge cases (auth/policy/404s) — it only
 * chains the four behaviours together against a shared fake disk.
 */
it('uploads images, rejects invalid files, swaps the cover, and cleans up files on vehicle delete', function () {
    Storage::fake('public');
    $owner = User::factory()->create();
    $vehicle = vehicleOwnedByForImageFlow($owner);

    // 1. Upload several files at once — all persisted and stored on disk.
    $uploadResponse = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('back.jpg'),
            UploadedFile::fake()->image('side.jpg'),
        ],
    ]);

    $uploadResponse->assertCreated();
    $uploadResponse->assertJsonCount(3, 'data');

    $images = VehicleImage::query()->where('vehicle_id', $vehicle->id)->orderBy('id')->get();
    expect($images)->toHaveCount(3);

    foreach ($images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }

    // The first uploaded image automatically became the vehicle's cover.
    $cover = $images->firstWhere('is_cover', true);
    expect($cover)->not->toBeNull();

    // 2. An oversized file and a non-image file are both rejected with 422
    // problem+json, and nothing is written to disk for them.
    $oversizedResponse = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg')],
    ]);
    $oversizedResponse->assertProblemJson(status: 422, errorKeys: ['files.0']);

    $nonImageResponse = $this->actingAs($owner)->postJson("/api/vehicles/{$vehicle->id}/images", [
        'files' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
    ]);
    $nonImageResponse->assertProblemJson(status: 422, errorKeys: ['files.0']);

    expect(VehicleImage::query()->where('vehicle_id', $vehicle->id)->count())->toBe(3);

    // No file was written to disk for either rejected upload: the vehicle's
    // folder still only contains the 3 files from the successful batch.
    expect(Storage::disk('public')->allFiles("vehicles/{$vehicle->id}"))->toHaveCount(3);

    // 3. Setting a new cover leaves exactly one image with `is_cover = true`
    // for the vehicle — starting from a vehicle that already has a cover, so
    // the swap is actually exercised.
    $newCover = $images->firstWhere('is_cover', false);

    $coverResponse = $this->actingAs($owner)->patchJson(
        "/api/vehicles/{$vehicle->id}/images/{$newCover->id}/cover"
    );

    $coverResponse->assertOk();
    $coverResponse->assertJsonPath('data.id', $newCover->id);
    $coverResponse->assertJsonPath('data.is_cover', true);

    expect($cover->refresh()->is_cover)->toBeFalse();
    expect($newCover->refresh()->is_cover)->toBeTrue();
    expect(
        VehicleImage::query()->where('vehicle_id', $vehicle->id)->where('is_cover', true)->count()
    )->toBe(1);

    // 4. Deleting the vehicle removes its image files from the fake disk.
    $paths = $images->pluck('path')->all();

    $deleteResponse = $this->actingAs($owner)->deleteJson("/api/vehicles/{$vehicle->id}");

    $deleteResponse->assertNoContent();
    $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});
