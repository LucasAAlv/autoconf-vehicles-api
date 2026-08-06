<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * Transform the vehicle into an array.
     *
     * `cambio`/`combustivel` are exposed via `->value`, not the enum
     * instance itself: PHP would serialize a backed enum to that same
     * scalar automatically, but spelling it out keeps the output type
     * obvious from reading this method alone.
     *
     * `creator`/`updater` are trimmed down to `id`/`name` on purpose — the
     * full `User` model is never exposed here, so nothing like the password
     * hash can leak through this resource. Either relation is `null` when
     * unset (a vehicle created before an observer stamped it, for example).
     *
     * `images` is a placeholder: `VehicleImage` does not exist yet (M4), so
     * this always reports an empty array until that model lands.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'placa'       => $this->placa,
            'chassi'      => $this->chassi,
            'marca'       => $this->marca,
            'modelo'      => $this->modelo,
            'versao'      => $this->versao,
            'valor_venda' => $this->valor_venda,
            'cor'         => $this->cor,
            'km'          => $this->km,
            'cambio'      => $this->cambio->value,
            'combustivel' => $this->combustivel->value,
            'user_id'     => $this->user_id,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater ? [
                'id'   => $this->updater->id,
                'name' => $this->updater->name,
            ] : null),
            'images' => [],
        ];
    }
}
