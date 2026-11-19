<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Returning a JsonResponse (or AuthResource) gives explicit control over HTTP status codes,
     * headers, and strict PHP return type-hinting, whereas returning a raw array always defaults
     * to a 200 OK status code.
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute(RegisterData::fromRequest($request));

        return (new AuthResource($result))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute(LoginData::fromRequest($request));

        return (new AuthResource($result))
            ->response();
    }

    public function logout(Request $request, LogoutUserAction $action): JsonResponse
    {
        $action->execute($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
