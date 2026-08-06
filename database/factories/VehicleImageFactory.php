<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\VehicleImage>
 */
class VehicleImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'path' => 'vehicles/'.fake()->numberBetween(1, 999).'/'.fake()->uuid().'.jpg',
            'is_cover' => false,
        ];
    }

    /**
     * Mark this image as the vehicle's cover.
     *
     * `vehicle_images` has a partial unique index
     * (`unique(vehicle_id) where is_cover = true`) enforcing exactly one
     * cover per vehicle, so this state is only correct when applied to a
     * single image per vehicle in a given bulk create — applying it to more
     * than one image sharing the same `vehicle_id` in the same batch (e.g.
     * `VehicleImage::factory()->count(3)->for($vehicle)->cover()->create()`)
     * violates that index at the database level. Callers are responsible
     * for only ever marking one image per vehicle as the cover.
     */
    public function cover(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_cover' => true,
        ]);
    }
}
