<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Any authenticated user may list vehicles.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user may view a single vehicle, regardless of
     * ownership — the acceptance criteria only gates `update`/`delete` on
     * ownership or admin status.
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return true;
    }

    /**
     * Only the vehicle's owner or an admin may update it.
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id || $user->is_admin;
    }

    /**
     * Only the vehicle's owner or an admin may delete it.
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->user_id || $user->is_admin;
    }
}
