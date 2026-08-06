<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
}
