<?php

namespace App\DTOs;

use App\Models\User;

readonly class AuthResult
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
