<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'email_verified' => $this->hasVerifiedEmail(),
            'store' => $this->when($this->role->value === 'seller', fn () => $this->store ? new StoreResource($this->store) : null),
        ];
    }
}
