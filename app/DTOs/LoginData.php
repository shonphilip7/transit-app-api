<?php

namespace App\DTOs;

use App\Http\Requests\LoginRequest;

readonly class LoginData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );
    }
}
