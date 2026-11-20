<?php

namespace App\Http\Controllers;

use App\Actions\Transit\GetRoutesAction;
use App\Http\Resources\RouteResource;
use Illuminate\Http\JsonResponse;

class RouteController extends Controller
{
    public function index(GetRoutesAction $action): JsonResponse
    {
        $routes = $action->execute();

        // Returns a list of many which is why collection is used
        return response()->json(RouteResource::collection($routes));
    }
}
