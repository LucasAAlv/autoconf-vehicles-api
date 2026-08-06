<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleImage\StoreVehicleImageRequest;
use App\Http\Resources\VehicleImageResource;
use App\Models\Vehicle;
use App\Services\VehicleImageService;
use Illuminate\Http\JsonResponse;

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
}
