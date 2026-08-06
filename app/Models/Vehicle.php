<?php

namespace App\Models;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Observers\VehicleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(VehicleObserver::class)]
class Vehicle extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * `user_id` is deliberately excluded: ownership is set server-side from
     * the authenticated user, never from client input, so it cannot be
     * mass-assigned even if a caller includes it in the request payload.
     *
     * `created_by`/`updated_by` are excluded for the same reason: they are
     * stamped by `VehicleObserver` from the authenticated user on the
     * `creating`/`updating` events, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'placa',
        'chassi',
        'marca',
        'modelo',
        'versao',
        'valor_venda',
        'cor',
        'km',
        'cambio',
        'combustivel',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_venda' => 'decimal:2',
            'km'          => 'integer',
            'cambio'      => Cambio::class,
            'combustivel' => Combustivel::class,
        ];
    }

    /**
     * The user who owns this vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who originally created this vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last updated this vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The images belonging to this vehicle.
     *
     * @return HasMany<VehicleImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class);
    }

    /**
     * Apply the allow-listed, hand-written filters from `IndexVehicleRequest`
     * (ADR-008 — no `spatie/laravel-query-builder`) to a listing query.
     *
     * `marca`, `modelo` and `placa` each narrow the query independently by a
     * case-insensitive partial match (`ILIKE '%value%'`) against their own
     * column. `q` is a single free-text term matched the same way — partial,
     * case-insensitive — but against `placa` OR `marca` OR `modelo`: any one
     * of the three hitting is enough. That `OR` group is wrapped in its own
     * `where(fn () => ...)` closure so it stays self-contained and never
     * leaks out to `orWhere` the other filters, which is what lets every
     * present filter narrow the result set further instead of the query
     * turning into a union the moment more than one filter is given. All
     * four filters use `ILIKE` (not `LIKE`) for case-insensitive matching,
     * which is PostgreSQL-specific — consistent with the rest of this
     * codebase already requiring PostgreSQL. Values are always bound as
     * query parameters (`where($column, 'ilike', $value)`), never
     * interpolated into a raw SQL string.
     *
     * @param  Builder<Vehicle>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Vehicle>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['marca'] ?? null, fn (Builder $query, string $marca) => $query->where('marca', 'ilike', "%{$marca}%"))
            ->when($filters['modelo'] ?? null, fn (Builder $query, string $modelo) => $query->where('modelo', 'ilike', "%{$modelo}%"))
            ->when($filters['placa'] ?? null, fn (Builder $query, string $placa) => $query->where('placa', 'ilike', "%{$placa}%"))
            ->when($filters['q'] ?? null, function (Builder $query, string $q) {
                $query->where(function (Builder $query) use ($q) {
                    $query->where('placa', 'ilike', "%{$q}%")
                        ->orWhere('marca', 'ilike', "%{$q}%")
                        ->orWhere('modelo', 'ilike', "%{$q}%");
                });
            });
    }
}
