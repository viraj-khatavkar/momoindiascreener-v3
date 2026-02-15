<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'address_line_one' => $this->address_line_one,
            'address_line_two' => $this->address_line_two,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'phone' => $this->phone,
            'postal_code' => $this->postal_code,
        ];
    }
}
