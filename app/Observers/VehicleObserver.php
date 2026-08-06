<?php

namespace App\Observers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;

class VehicleObserver
{
    /**
     * Stamp `created_by` and `updated_by` with the authenticated user id
     * when a vehicle is created.
     *
     * Both are set on creation so that a freshly created vehicle already
     * has a consistent `updated_by`, matching its `created_by`, before any
     * edit has ever happened.
     */
    public function creating(Vehicle $vehicle): void
    {
        $vehicle->created_by = Auth::id();
        $vehicle->updated_by = Auth::id();
    }

    /**
     * Stamp `updated_by` with the authenticated user id on every update.
     *
     * `created_by` is deliberately left untouched here: it must keep
     * pointing at whoever originally created the vehicle, regardless of
     * how many other users edit it afterwards.
     */
    public function updating(Vehicle $vehicle): void
    {
        $vehicle->updated_by = Auth::id();
    }
}
