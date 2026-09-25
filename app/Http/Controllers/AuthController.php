<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): array
    {
        $fields = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|max:255',
        ]);
        $user = User::create($fields);
        $token = $user->createToken($request->name);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    public function login(Request $request): array
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => 'required',
        ]);
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return [
                'message' => 'Email entered does not exist.',
            ];
        }
        if (! Hash::check($request->password, $user->password)) {
            return [
                'message' => 'Password is incorrect.',
            ];
        }
        $token = $user->createToken($user->name);

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    public function logout(Request $request): array
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->tokens()->delete();

        return [
            'message' => 'You are logged out.',
        ];
    }
}
