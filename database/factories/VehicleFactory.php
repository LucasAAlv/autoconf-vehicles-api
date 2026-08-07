<?php

namespace Database\Factories;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Monotonically increasing counter used to guarantee `placa` uniqueness
     * across bulk creation within a single process (e.g. `Vehicle::factory()
     * ->count(50)->create()`). Relying solely on `fake()->unique()` would
     * only reduce collisions probabilistically (and throws an
     * `OverflowException` if it can't find a free value after enough
     * retries); encoding this counter directly into the generated plate
     * removes the possibility of a collision entirely, up to the
     * counter-space described on {@see self::generatePlaca()}.
     */
    private static int $placaSequence = 0;

    /**
     * Same rationale as {@see self::$placaSequence}, but for `chassi`.
     */
    private static int $chassiSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placa' => $this->generatePlaca(),
            'chassi' => $this->generateChassi(),
            'marca' => fake()->randomElement([
                'Volkswagen', 'Fiat', 'Chevrolet', 'Toyota', 'Honda',
                'Hyundai', 'Ford', 'Renault', 'Jeep', 'Nissan',
            ]),
            'modelo' => fake()->randomElement([
                'Gol', 'Onix', 'HB20', 'Corolla', 'Civic',
                'Compass', 'Kicks', 'Argo', 'Cronos', 'Tracker',
            ]),
            'versao' => fake()->randomElement([
                '1.0', '1.6 Turbo', 'GLI', 'LTZ', 'Exclusive', 'Titanium', 'Comfortline',
            ]),
            'valor_venda' => fake()->randomFloat(2, 10000, 200000),
            'cor' => fake()->safeColorName(),
            'km' => fake()->numberBetween(0, 300000),
            'cambio' => fake()->randomElement(Cambio::cases()),
            'combustivel' => fake()->randomElement(Combustivel::cases()),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Generate a plate matching the Mercosul shape enforced by
     * `StoreVehicleRequest` (`/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/i`): 3
     * letters, a digit, a letter, then 2 digits.
     *
     * The leading 3 letters are random (they only exist for plausibility).
     * Uniqueness is guaranteed instead by deterministically encoding
     * `self::$placaSequence` into the trailing digit+letter+digit+digit
     * segment via `intdiv`/`%`, a 10 * 26 * 10 * 10 = 26,000-value space
     * that is walked without repeats before it would ever wrap around —
     * far beyond what any bulk factory call in this codebase creates.
     */
    private function generatePlaca(): string
    {
        $sequence = self::$placaSequence++;

        $d3 = $sequence % 10;
        $sequence = intdiv($sequence, 10);

        $d2 = $sequence % 10;
        $sequence = intdiv($sequence, 10);

        $letter = chr(65 + ($sequence % 26));
        $sequence = intdiv($sequence, 26);

        $d1 = $sequence % 10;

        $prefix = Str::upper(fake()->lexify('???'));

        return "{$prefix}{$d1}{$letter}{$d2}{$d3}";
    }

    /**
     * Generate a 17-character alphanumeric `chassi` (VIN length, enforced by
     * `StoreVehicleRequest`'s `size:17` + `alpha_num` rules).
     *
     * The first 11 characters are random for plausibility. Uniqueness is
     * guaranteed instead by base-36 encoding `self::$chassiSequence` into
     * the trailing 6 characters, a 36^6 ≈ 2.18 billion-value space walked
     * without repeats — far beyond what any bulk factory call in this
     * codebase creates.
     */
    private function generateChassi(): string
    {
        $sequence = self::$chassiSequence++;

        $suffix = Str::upper(str_pad(base_convert((string) $sequence, 10, 36), 6, '0', STR_PAD_LEFT));

        $prefix = Str::upper(fake()->lexify(str_repeat('?', 11)));

        return "{$prefix}{$suffix}";
    }
}
