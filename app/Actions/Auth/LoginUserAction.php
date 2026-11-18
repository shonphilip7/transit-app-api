<?php

namespace App\Actions\Auth;

use App\DTOs\AuthResult;
use App\DTOs\LoginData;
use App\Enums\TokenName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(LoginData $data): AuthResult
    {
        $user = User::where('email', $data->email)->first();
        if (! $user || ! Hash::check($data->password, $user->password)) {
            // Throwing validation instead of returning a message is the industry standard
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }
        $token = $user->createToken(TokenName::Auth->value)->plainTextToken;

        return new AuthResult($user, $token);
    }
}
