<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\VehicleImage
 */
class VehicleImageResource extends JsonResource
{
    /**
     * `path` is kept as-is (relative to the public disk, matching how it
     * is stored in `vehicle_images.path`) alongside `url`, a ready-to-use
     * public URL built from that same path — the SPA consumes `url`
     * directly and never needs to know the disk layout.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'url' => Storage::disk('public')->url($this->path),
            'is_cover' => $this->is_cover,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
