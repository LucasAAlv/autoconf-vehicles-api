<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleImage\StoreVehicleImageRequest;
use App\Http\Resources\VehicleImageResource;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Services\VehicleImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VehicleImageController extends Controller
{
    public function __construct(private readonly VehicleImageService $vehicleImageService)
    {
    }

    /**
     * Upload one or more images for a vehicle.
     *
     * `VehiclePolicy::manageImages` gates this the same way `update`/`delete`
     * already gate their own actions: only the vehicle's owner or an
     * `is_admin` user may add images to it.
     */
    public function store(StoreVehicleImageRequest $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('manageImages', $vehicle);

        $images = $this->vehicleImageService->upload($vehicle, $request->file('files'));

        return VehicleImageResource::collection($images)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Set the cover of a vehicle to one of its own images.
     *
     * `{imageId}` is resolved manually (`vehicle_id`-scoped query +
     * `findOrFail`) instead of via implicit nested route-model binding:
     * nested binding would need `{vehicle}` and `{image}` to be related
     * through a named Eloquent relationship matching the route segment,
     * whereas here the check that the image actually belongs to this
     * vehicle *is* the interesting behaviour (an image from a different
     * vehicle must 404, not 403), so it is spelled out explicitly.
     * `findOrFail` throws a `ModelNotFoundException`, which the exception
     * handler in `bootstrap/app.php` already rewrites into a 404
     * `application/problem+json` response — nothing extra to do here for
     * either "wrong vehicle" or "no such image".
     */
    public function setCover(Vehicle $vehicle, int $imageId): VehicleImageResource
    {
        $this->authorize('manageImages', $vehicle);

        $image = VehicleImage::query()
            ->where('vehicle_id', $vehicle->id)
            ->findOrFail($imageId);

        $image = $this->vehicleImageService->setCover($vehicle, $image);

        return new VehicleImageResource($image);
    }

    /**
     * Delete a single image belonging to a vehicle.
     *
     * The image is looked up through `$vehicle->images()` rather than a
     * global `VehicleImage::findOrFail()` (or implicit route-model binding
     * on `{imageId}`): this guarantees an image belonging to a different
     * vehicle 404s exactly like one that doesn't exist at all, instead of
     * leaking a 403 (or succeeding) for an id that is real but not "this
     * vehicle's".
     */
    public function destroy(Vehicle $vehicle, string $imageId): Response
    {
        $this->authorize('manageImages', $vehicle);

        $image = $vehicle->images()->findOrFail($imageId);

        $this->vehicleImageService->destroy($image);

        return response()->noContent();
    }
}
