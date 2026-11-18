<?php

namespace App\Actions\Auth;

use App\DTOs\AuthResult;
use App\DTOs\RegisterData;
use App\Enums\TokenName;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class RegisterUserAction
{
    public function execute(RegisterData $data): AuthResult
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password, // Automatically hashed by casts function in the User model
        ]);
        /*
         Not required right now but might be for the future. Register an event for listner classes
         like using an email verification class to send verification at the time of registration.
        */
        event(new Registered($user));
        $token = $user->createToken(TokenName::Auth->value)->plainTextToken;

        return new AuthResult($user, $token);
    }
}
