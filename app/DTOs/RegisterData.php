<?php

namespace App\DTOs;

use App\Http\Requests\RegisterRequest;

class RegisterData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );
    }
}
