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
     * Allow-list of columns a client may sort by via the `sort` query
     * parameter on `GET /vehicles` (issue #31), keyed by the field name a
     * client is allowed to send and mapped to the real column to sort on.
     *
     * This map is the single point of trust for sorting: both
     * `IndexVehicleRequest` (to reject an unlisted field with a 422 before
     * the query ever runs) and `scopeSort()` (to build the query) key off
     * this same array, so there is exactly one place that decides which
     * fields are sortable. A client's field name is only ever used to look
     * itself up here — the value on the other side of the lookup (the real
     * column name) is what gets passed to `orderBy()`, never the raw client
     * string, which is what keeps this immune to SQL injection regardless
     * of what a caller sends.
     *
     * `chassi` and `placa` are deliberately left out even though they are
     * real columns: they're identifiers, not the kind of attribute a
     * listing is usually sorted by, so keeping the list to simple scalar
     * attributes plus `created_at` keeps it small and obviously safe.
     *
     * @var array<string, string>
     */
    public const array SORTABLE_COLUMNS = [
        'km' => 'km',
        'valor_venda' => 'valor_venda',
        'marca' => 'marca',
        'modelo' => 'modelo',
        'created_at' => 'created_at',
    ];

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

    /**
     * Apply the multi-field sort requested via `IndexVehicleRequest`'s `sort`
     * parameter (issue #31, ADR-008 — hand-written allow-list, no
     * `spatie/laravel-query-builder`) to a listing query.
     *
     * `$sort` is a comma-separated list of fields, e.g. `km,-valor_venda`: a
     * leading `-` means descending, its absence means ascending. Each field
     * is looked up in `SORTABLE_COLUMNS` and only the resulting column name
     * — never the raw client-supplied field name — is passed to
     * `orderBy()`, which is what makes this immune to SQL injection
     * regardless of what a caller sends. `IndexVehicleRequest` already
     * rejects any field that isn't in `SORTABLE_COLUMNS` with a 422 before
     * this scope ever runs, so the `$column !== null` check here is just
     * defense in depth, not the primary safeguard.
     *
     * This only appends the requested `orderBy()` calls; it never adds the
     * final tiebreak by `id` itself. `VehicleController::index()` chains
     * `->orderBy('id')` after `->sort()`, so two rows equal on every
     * requested field still come back in a stable order — Eloquent/query
     * builder appends multiple `orderBy()` calls in call order, so the
     * requested fields necessarily take precedence over that trailing `id`
     * tiebreak.
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        if (! $sort) {
            return $query;
        }

        foreach (explode(',', $sort) as $token) {
            $direction = 'asc';
            $field = $token;

            if (str_starts_with($token, '-')) {
                $direction = 'desc';
                $field = substr($token, 1);
            }

            $column = self::SORTABLE_COLUMNS[$field] ?? null;

            if ($column !== null) {
                $query->orderBy($column, $direction);
            }
        }

        return $query;
    }
}
