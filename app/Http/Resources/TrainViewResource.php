<?php

namespace App\Http\Resources;

use App\DTOs\TrainViewResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainViewResultData */
class TrainViewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            $this->routeId => [
                'Inbound' => ScheduleTripResource::collection($this->inbound),
                'Outbound' => ScheduleTripResource::collection($this->outbound),
            ],
        ];
    }
}
