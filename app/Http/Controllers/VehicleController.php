<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vehicle\IndexVehicleRequest;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\VehicleImageService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    /**
     * Default page size when the client doesn't send `per_page`.
     */
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Hard cap on `per_page` so a client can't request an unbounded page
     * size — a value above this is clamped down to it rather than
     * rejected or ignored.
     */
    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly VehicleImageService $vehicleImageService)
    {
    }

    /**
     * List vehicles, paginated.
     *
     * `VehiclePolicy::viewAny` already allows any authenticated user, so
     * this lists every vehicle in the system, not just the caller's own —
     * consistent with `show()`, which likewise doesn't gate on ownership.
     *
     * `Vehicle::query()` is ordered by `id` (so pagination is deterministic)
     * and paginated. `q`/`marca`/`modelo`/`placa` filtering is delegated to
     * `Vehicle::scopeFilter()` (issue #30) so this method stays a thin
     * pass-through of the validated input; sorting (issue #31) extends this
     * same query rather than replacing it, via `Vehicle::scopeSort()`, and is
     * chained *before* the trailing `orderBy('id')` so the requested fields
     * take precedence and `id` only breaks ties among rows equal on all of
     * them.
     *
     * `per_page` is clamped to `MAX_PER_PAGE` instead of erroring, so a
     * client asking for an unbounded page size just gets the cap back.
     * `VehicleResource::collection()` on a paginator carries `total`,
     * `current_page`, `last_page` and `per_page` through automatically in
     * the response's `meta` envelope, so no custom collection class is
     * needed to satisfy those fields.
     */
    public function index(IndexVehicleRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $perPage = min(
            (int) ($request->validated('per_page') ?? self::DEFAULT_PER_PAGE),
            self::MAX_PER_PAGE,
        );

        $vehicles = Vehicle::query()
            ->filter($request->validated())
            ->sort($request->validated('sort'))
            ->orderBy('id')
            ->paginate($perPage);

        return VehicleResource::collection($vehicles);
    }

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
     * `vehicle_images.vehicle_id` is `cascadeOnDelete()` at the raw
     * PostgreSQL level, so `VehicleImage` rows disappear automatically along
     * with the vehicle — but that cascade never fires an Eloquent event, so
     * `VehicleImageService::deleteAllForVehicle()` is called, inside the same
     * transaction, to also clean up the physical files from the public disk
     * (deferred to after the commit, so a rollback never leaves the vehicle
     * gone but its images' files still on disk, or vice versa).
     */
    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        DB::transaction(function () use ($vehicle) {
            $this->vehicleImageService->deleteAllForVehicle($vehicle);

            $vehicle->delete();
        });

        return response()->noContent();
    }
}
