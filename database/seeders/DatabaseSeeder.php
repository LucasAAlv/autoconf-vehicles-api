<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Creates one admin and one regular user (#34's acceptance criteria),
     * then 10 vehicles with placeholder images. Vehicles are attached to the
     * non-admin user rather than the admin: this gives the admin's "manage
     * anyone's vehicle" policy (`VehiclePolicy::update()`/`delete()`) an
     * actual owned-by-someone-else record to demonstrate against, whereas
     * seeding them under the admin itself wouldn't exercise that distinction
     * at all.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        Vehicle::factory()
            ->count(10)
            ->for($user)
            ->create()
            ->each(function (Vehicle $vehicle): void {
                // Non-cover images are created first, then the single cover
                // image, so the batch never has more than one `is_cover =
                // true` row per vehicle at once — required by the partial
                // unique index on `vehicle_images` (see #33's
                // `VehicleImageFactory::cover()`).
                VehicleImage::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->for($vehicle)
                    ->create();

                VehicleImage::factory()
                    ->for($vehicle)
                    ->cover()
                    ->create();
            });
    }
}
