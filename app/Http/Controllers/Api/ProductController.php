<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\InvalidatesPromptCache;
use App\Support\NormalizadorStockTallas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use InvalidatesPromptCache;

    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'variants'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->search.'%');
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        $products = $query->paginate($request->input('per_page', 20));

        return ProductResource::collection($products)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'price_tiktok' => 'nullable|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'discount_active' => 'nullable|boolean',
            'oculto' => 'nullable|boolean',
            'category_id' => 'nullable|exists:categories,id',
            'tags_ia' => 'nullable|array',
            'variants' => 'required|array|min:1',
            'variants.*.color' => 'required|string',
            'variants.*.image_url' => 'nullable|string',
            'variants.*.sizes_stock' => 'nullable|array',
        ]);

        if (
            ! empty($validated['discount_active'])
            && isset($validated['discount'])
            && $validated['discount'] > $validated['price']
        ) {
            return response()->json(['message' => 'El descuento no puede ser mayor que el precio normal'], 422);
        }

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'price_tiktok' => $validated['price_tiktok'] ?? null,
            'discount' => $validated['discount'] ?? null,
            'discount_active' => (bool) ($validated['discount_active'] ?? false),
            'category_id' => $validated['category_id'] ?? null,
            'status' => Product::ESTADO_DISPONIBLE,
            'tags_ia' => $validated['tags_ia'] ?? [],
        ]);

        foreach ($validated['variants'] as $variant) {
            $product->variants()->create([
                'color' => $variant['color'],
                'image_url' => $variant['image_url'] ?? null,
                'sizes_stock' => NormalizadorStockTallas::normalize($variant['sizes_stock'] ?? []),
            ]);
        }

        $product->refresh()->load('variants');
        $this->aplicarEstadoProducto($product, (bool) ($validated['oculto'] ?? false));

        $this->invalidarCachePrompt();

        return (new ProductResource($product->load('variants')))->response()->setStatusCode(201);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'variants'])->findOrFail($id);

        return (new ProductResource($product))->response();
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0.01',
            'price_tiktok' => 'nullable|numeric|min:0.01',
            'discount' => 'nullable|numeric|min:0',
            'discount_active' => 'nullable|boolean',
            'oculto' => 'nullable|boolean',
            'category_id' => 'nullable|exists:categories,id',
            'tags_ia' => 'nullable|array',
            'variants' => 'sometimes|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.color' => 'required|string',
            'variants.*.image_url' => 'nullable|string',
            'variants.*.sizes_stock' => 'nullable|array',
        ]);

        $newPrice = $validated['price'] ?? $product->price;
        $newDiscount = $validated['discount'] ?? $product->discount;
        $discountActive = array_key_exists('discount_active', $validated)
            ? (bool) $validated['discount_active']
            : (bool) $product->discount_active;

        if ($discountActive && $newDiscount && $newDiscount > $newPrice) {
            return response()->json(['message' => 'El descuento no puede ser mayor que el precio normal'], 422);
        }

        $product->update([
            'name' => $validated['name'] ?? $product->name,
            'description' => $validated['description'] ?? $product->description,
            'price' => $newPrice,
            'price_tiktok' => array_key_exists('price_tiktok', $validated)
                ? $validated['price_tiktok']
                : $product->price_tiktok,
            'discount' => $validated['discount'] ?? $product->discount,
            'discount_active' => $discountActive,
            'category_id' => $validated['category_id'] ?? $product->category_id,
            'tags_ia' => $validated['tags_ia'] ?? $product->tags_ia,
        ]);

        if (isset($validated['variants'])) {
            $keptIds = [];

            foreach ($validated['variants'] as $variantData) {
                $payload = [
                    'color' => $variantData['color'],
                    'image_url' => $variantData['image_url'] ?? null,
                    'sizes_stock' => NormalizadorStockTallas::normalize($variantData['sizes_stock'] ?? []),
                ];

                if (! empty($variantData['id'])) {
                    $existing = $product->variants()->find($variantData['id']);
                    if ($existing) {
                        if ($existing->image_path) {
                            unset($payload['image_url']);
                        }
                        $existing->update($payload);
                        $keptIds[] = $existing->id;

                        continue;
                    }
                }

                $created = $product->variants()->create($payload);
                $keptIds[] = $created->id;
            }

            $removed = $product->variants()->whereNotIn('id', $keptIds)->get();
            foreach ($removed as $variant) {
                if ($variant->image_path) {
                    Storage::disk('public')->delete($variant->image_path);
                }
                $variant->delete();
            }
        }

        $product->refresh()->load('variants');

        if (array_key_exists('oculto', $validated)) {
            $this->aplicarEstadoProducto($product, (bool) $validated['oculto']);
        } elseif ($product->status !== Product::ESTADO_OCULTO) {
            $product->sincronizarEstadoPorStock();
        }

        $this->invalidarCachePrompt();

        return (new ProductResource($product->load('variants')))->response();
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::withCount('sales')->findOrFail($id);

        if ($product->sales_count > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el producto porque tiene ventas asociadas.',
            ], 422);
        }

        $product->delete();
        $this->invalidarCachePrompt();

        return response()->json(null, 204);
    }

    private function aplicarEstadoProducto(Product $product, bool $oculto): void
    {
        if ($oculto) {
            $product->marcarComoOculto();

            return;
        }

        $product->desmarcarOculto();
    }
}
