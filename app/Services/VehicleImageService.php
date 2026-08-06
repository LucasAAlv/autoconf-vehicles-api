<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VehicleImageService
{
    /**
     * Store the given files for a vehicle on the public disk, one folder
     * per vehicle (`vehicles/{vehicle_id}/`), and persist a `VehicleImage`
     * row for each.
     *
     * The first image a vehicle ever receives becomes its cover
     * automatically: if the vehicle currently has zero images, the first
     * file of this batch (in submission order) is flagged `is_cover =
     * true` and every other file — in this batch or any later one — is
     * not. Once a vehicle has at least one image, nothing uploaded here
     * ever becomes cover; changing the cover afterwards is a separate
     * concern (the dedicated "set cover" endpoint).
     *
     * The row inserts are wrapped in a transaction so a failure partway
     * through a batch never leaves a partial, inconsistent set of rows —
     * and, in particular, never leaves a vehicle with two `is_cover` rows
     * or none when it should have exactly one. The partial unique index
     * on `vehicle_images` backs this up at the database level. Physical
     * files are written as each row is created rather than in a separate
     * pass; a rollback here does not delete files already written to
     * disk, which is an accepted trade-off since a mid-batch failure is
     * expected to be rare and an orphaned file is harmless.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, VehicleImage>
     */
    public function upload(Vehicle $vehicle, array $files): Collection
    {
        $shouldAssignCover = ! $vehicle->images()->exists();

        return DB::transaction(function () use ($vehicle, $files, $shouldAssignCover) {
            $images = collect();

            foreach (array_values($files) as $file) {
                $images->push($vehicle->images()->create([
                    'path' => $file->store("vehicles/{$vehicle->id}", 'public'),
                    'is_cover' => $shouldAssignCover && $images->isEmpty(),
                ]));
            }

            return $images;
        });
    }

    /**
     * Make the given image the vehicle's cover.
     *
     * The swap is wrapped in a transaction and always clears the current
     * cover (if any) before setting the new one, in that order. Doing it
     * in the opposite order — or outside a transaction — would momentarily
     * (or, on a race, permanently) leave two rows with `is_cover = true`
     * for the same vehicle, which the partial unique index on
     * `vehicle_images` forbids. When `$image` is already the cover, the
     * "clear" step affects zero rows and the "set" step is a no-op save,
     * so this is safe to call unconditionally without a special case.
     */
    public function setCover(Vehicle $vehicle, VehicleImage $image): VehicleImage
    {
        DB::transaction(function () use ($vehicle, $image) {
            $vehicle->images()
                ->where('is_cover', true)
                ->where('id', '!=', $image->id)
                ->update(['is_cover' => false]);

            $image->update(['is_cover' => true]);
        });

        return $image;
    }

    /**
     * Delete a single `VehicleImage`: remove its DB row and, once the
     * transaction actually commits, its physical file from the public disk.
     *
     * Settled product decision: deleting the cover image leaves the vehicle
     * with zero cover images. No remaining image is ever auto-promoted to
     * cover — every other image simply stays (or already is) `is_cover =
     * false`, and picking a new cover is a separate, deliberate call to the
     * "set cover" endpoint.
     *
     * The physical file is removed via `DB::afterCommit()` rather than
     * right after `$image->delete()`, mirroring the transaction technique
     * `VehicleController::destroy()` already uses: if anything makes the
     * transaction roll back, the file must still exist on disk, so deleting
     * it is deferred until the commit is guaranteed to have happened.
     */
    public function destroy(VehicleImage $image): void
    {
        DB::transaction(function () use ($image) {
            $path = $image->path;

            $image->delete();

            DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        });
    }

    /**
     * Delete every physical file belonging to a vehicle's images, meant to be
     * called as part of vehicle deletion.
     *
     * `vehicle_images.vehicle_id` is `cascadeOnDelete()` at the raw
     * PostgreSQL level, so the rows themselves disappear automatically when
     * the vehicle row is deleted — but that is a database-level cascade, not
     * an Eloquent event, so no `VehicleImage` model event fires and nothing
     * would otherwise remove their physical files from disk.
     *
     * Paths are collected before the caller deletes the vehicle, and the
     * actual disk deletion is deferred to `DB::afterCommit()`, mirroring
     * `destroy()` above: if the surrounding transaction rolls back, the
     * files must still exist, so removing them is deferred until the commit
     * is guaranteed to have happened.
     */
    public function deleteAllForVehicle(Vehicle $vehicle): void
    {
        $paths = $vehicle->images()->pluck('path');

        DB::afterCommit(function () use ($paths) {
            Storage::disk('public')->delete($paths->all());
        });
    }
}
