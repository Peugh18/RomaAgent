<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'price_tiktok' => $this->price_tiktok,
            'discount' => $this->discount,
            'discount_active' => $this->discount_active,
            'category_id' => $this->category_id,
            'tags_ia' => $this->tags_ia,
            'status' => $this->status,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'color' => $variant->color,
                'image_url' => $this->resolveVariantImageUrl($variant),
                'image_path' => $variant->image_path,
                'sizes_stock' => $variant->sizes_stock,
                'has_embedding' => !empty($variant->image_embedding),
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Resolve public URL for variant image.
     */
    private function resolveVariantImageUrl($variant): ?string
    {
        if (! empty($variant->image_path)) {
            return Storage::disk('public')->url($variant->image_path);
        }

        return $variant->image_url ?? null;
    }
}
