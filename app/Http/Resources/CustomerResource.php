<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_number' => $this->phone_number,
            'name' => $this->name,
            'notes' => $this->notes,
            'ia_paused' => $this->ia_paused,
            'ia_pause_reason' => $this->ia_pause_reason,
            'active_sale_id' => $this->active_sale_id,
            'last_inbound_at' => $this->last_inbound_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Sales summary
            'sales_count' => $this->whenCounted('sales'),
            'total_spent' => (float) ($this->total_spent ?? 0),

            // Recent sales (last 5)
            'recent_sales' => $this->when(
                $this->relationLoaded('sales'),
                fn () => $this->sales->sortByDesc('created_at')->take(5)->map(fn ($sale) => [
                    'id' => $sale->id,
                    'product_name' => $sale->product_name,
                    'total_amount' => (float) $sale->total_amount,
                    'status' => $sale->status->value ?? $sale->status,
                    'created_at' => $sale->created_at,
                    'payment_method' => $sale->payment_method,
                    'customer_data' => $sale->customer_data,
                ])->values()
            ),
        ];
    }
}
