<?php

namespace App\Http\Resources;

use App\DTOs\RouteData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RouteData */
class RouteResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array. Ensures public API outputs
     * the exact snake_case keys your consumers expect.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'release_name' => $this->releaseName,
            'route_id' => $this->routeId,
            'route_short_name' => $this->routeShortName,
            'route_long_name' => $this->routeLongName,
            'route_type' => $this->routeType,
        ];
    }
}
