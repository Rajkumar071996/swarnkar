<?php

namespace App\Http\Resources;

use App\Models\Udhaar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Udhaar
 */
class UdhaarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer->full_name),
            'invoice_no' => $this->invoice_no,
            'item_description' => $this->item_description,
            'principal_amount' => (float) $this->principal_amount,
            'amount_paid' => (float) $this->amount_paid,
            'outstanding_amount' => $this->outstandingAmount(),
            'issued_on' => $this->issued_on->toDateString(),
            'due_on' => $this->due_on->toDateString(),
            'settled_on' => $this->settled_on?->toDateString(),
            'days_overdue' => $this->status->isOutstanding() ? $this->daysOverdue() : 0,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
        ];
    }
}
