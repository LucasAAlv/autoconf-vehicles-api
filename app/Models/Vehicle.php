<?php

namespace App\Models;

use App\Enums\Cambio;
use App\Enums\Combustivel;
use App\Observers\VehicleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
