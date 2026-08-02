<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            // Identifiers stay masked in API responses. The client already knows
            // the number it searched with and has no need for the rest.
            'mobile_masked' => $this->maskedMobile(),
            'pan_masked' => $this->maskedPan(),
            'aadhaar_last4' => $this->aadhaar_last4,
            'city' => $this->city,
            'state' => $this->state,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
