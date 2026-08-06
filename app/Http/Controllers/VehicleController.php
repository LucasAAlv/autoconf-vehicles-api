<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    /**
     * Create a new vehicle owned by the authenticated user.
     *
     * `user_id` is not part of `$request->validated()` — it is not
     * validated input at all, it is assigned directly from the
     * authenticated user, so a client can never influence it by including
     * it in the payload (`Vehicle::$fillable` also excludes it, as a second
     * line of defense). `created_by`/`updated_by` are stamped separately by
     * `VehicleObserver` on the `creating` event.
     */
    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = new Vehicle($request->validated());
        $vehicle->user_id = $request->user()->id;
        $vehicle->save();

        // Loaded (rather than left unset) so the response shape matches
        // `show()` exactly: both always carry the audit relations and the
        // `images` collection (empty right after creation), never just
        // when they happen to already be in memory.
        $vehicle->load(['creator', 'updater', 'images']);

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single vehicle.
     *
     * `VehiclePolicy::view` currently allows any authenticated user
     * regardless of ownership, so this call trivially passes today — it is
     * wired in now so a later issue that tightens the policy doesn't need
     * to touch this controller.
     */
    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        $vehicle->load(['creator', 'updater', 'images']);

        return new VehicleResource($vehicle);
    }

    /**
     * Update a vehicle. Both `PUT` and `PATCH` route here and behave
     * identically: every field in `UpdateVehicleRequest` is `sometimes`, so
     * an absent field is simply left untouched by `fill()` rather than
     * nulled out — there is no "PUT replaces everything" distinction.
     *
     * `updated_by` is not set here — `VehicleObserver` stamps it from the
     * authenticated user on the `updating` event.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $this->authorize('update', $vehicle);

        $vehicle->fill($request->validated());
        $vehicle->save();

        $vehicle->load(['creator', 'updater', 'images']);

        return new VehicleResource($vehicle);
    }

    /**
     * Delete a vehicle.
     *
     * Wrapped in a transaction even though today it is a single-row
     * delete: `VehicleImage` (M4) will add image-row and physical-file
     * removal inside the same transaction later, without needing to
     * restructure this method.
     */
    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        DB::transaction(function () use ($vehicle) {
            $vehicle->delete();
        });

        return response()->noContent();
    }
}
