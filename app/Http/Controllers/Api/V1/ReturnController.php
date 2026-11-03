<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReturnType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnResource;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Returns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ReturnResource::collection(ReturnRequest::where('buyer_id', $request->user()->id)->with(['items.orderItem', 'subOrder'])->latest()->paginate(20));
    }

    public function show(Request $request, ReturnRequest $return): ReturnResource
    {
        abort_unless($return->buyer_id === $request->user()->id, 404);

        return new ReturnResource($return->load(['items.orderItem', 'subOrder']));
    }

    public function store(Request $request, Order $order, int $parcel, Returns $returns): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
        $subOrder = $order->subOrders()->with(['store', 'order'])->findOrFail($parcel);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer', 'min:0'],
            'type' => ['required', Rule::enum(ReturnType::class)],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = $returns->request($request->user(), $subOrder, $data['items'], ReturnType::from($data['type']), $data['reason'], $data['details'] ?? null);

        return (new ReturnResource($return->load(['items.orderItem', 'subOrder'])))->response()->setStatusCode(201);
    }
}
