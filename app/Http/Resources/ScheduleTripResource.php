<?php

namespace App\Http\Resources;

use App\DTOs\ScheduleTripData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScheduleTripData */
class ScheduleTripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'route_id' => $this->routeId,
            'release_name' => $this->releaseName,
            'trip_id' => $this->tripId,
            'service_id' => $this->serviceId,
            'direction_id' => $this->directionId,
            'arrival_time' => $this->arrivalTime,
            'stop_id' => $this->stopId,
            'stop_name' => $this->stopName,
            'stop_sequence' => $this->stopSequence,
        ];
    }
}
