<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    // Remove the data wrapper from the response as the frontend is not expecting this
    public static $wrap = null;

    /**
     * Defines the exact JSON structure your API returns to the client
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'token' => $this->token,
            'token_type' => 'Bearer',
        ];
    }
}
