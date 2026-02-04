<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'gateway_name' => $this->gateway_name,
            'gateway_reference' => $this->gateway_reference,
            'status' => $this->status,
            'gateway_response' => $this->gateway_response,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'order' => new OrderResource($this->whenLoaded('order')),
        ];
    }
}