<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Resources\VariantResource;
use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends SellerApiController
{
    public function index(Request $request): JsonResponse
    {
        $variants = ProductVariant::with('product:id,name,status')
            ->whereHas('product', fn ($q) => $q->where('store_id', $this->currentStore($request)->id))
            ->when($request->boolean('low'), fn ($q) => $q->where('stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD))
            ->orderBy('product_id')->orderBy('position')
            ->paginate(100);

        return response()->json([
            'data' => $variants->getCollection()->map(fn ($v) => [
                ...(new VariantResource($v))->resolve(),
                'product' => ['id' => $v->product->id, 'name' => $v->product->name, 'status' => $v->product->status->value],
            ]),
            'meta' => ['current_page' => $variants->currentPage(), 'last_page' => $variants->lastPage(), 'total' => $variants->total()],
        ]);
    }

    /**
     * Body: {"rows": {"<variant id>": {"price": 49900, "stock": 3}, …}}. Price changes on live products go for review.
     */
    public function update(Request $request, ProductService $service): JsonResponse
    {
        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.price' => ['required', 'integer', 'min:0'],
            'rows.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ])['rows'];

        $variants = ProductVariant::with('product')->whereIn('id', array_keys($rows))
            ->whereHas('product', fn ($q) => $q->where('store_id', $this->currentStore($request)->id))->get();

        $updated = 0;

        foreach ($variants as $variant) {
            $row = $rows[$variant->id];

            if ($variant->price !== (int) $row['price'] || $variant->stock !== (int) $row['stock']) {
                $service->updateInventory($variant, (int) $row['price'], (int) $row['stock']);
                $updated++;
            }
        }

        return response()->json(['updated' => $updated]);
    }
}
