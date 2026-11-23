<?php

namespace App\Http\Controllers;

use App\Actions\Transit\GetRouteStopsAction;
use App\DTOs\StopData;
use App\Http\Resources\StopResource;
use Illuminate\Http\JsonResponse;

class RouteStopsController extends Controller
{
    public function index(string $route, GetRouteStopsAction $action): JsonResponse
    {
        /*
         Temporary setup to return object instead of an array to keep in line with the current API structure.
         It needs to be replace by the commented code below.
        */
        $stops = $action->execute($route)
            ->map(fn (StopData $stop): StopResource => new StopResource($stop));

        return response()->json($stops);
        /*
         $stops = $action->execute($route);
         return response()->json(StopResource::collection($stops));
        */
    }
}
