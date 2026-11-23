<?php

namespace App\Http\Resources;

use App\DTOs\StopData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StopData */
class StopResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'route_id' => $this->routeId,
            'stop_id' => $this->stopId,
            'stop_name' => $this->stopName,
            'stop_sequence' => $this->stopSequence,
            'release_name' => $this->releaseName,
            'direction_id' => $this->directionId,
        ];
    }
}
