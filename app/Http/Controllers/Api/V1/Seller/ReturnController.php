<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Resources\ReturnResource;
use App\Models\ReturnRequest;
use App\Services\Returns;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReturnController extends SellerApiController
{
    public function __construct(private Returns $returns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ReturnResource::collection($this->query($request)->with(['items.orderItem', 'subOrder'])->latest()->paginate(25));
    }

    public function show(Request $request, string $return): ReturnResource
    {
        return $this->respond($this->find($request, $return));
    }

    public function approve(Request $request, string $return): ReturnResource
    {
        $model = $this->find($request, $return);
        $this->returns->approve($model, $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null);

        return $this->respond($model);
    }

    public function reject(Request $request, string $return): ReturnResource
    {
        $model = $this->find($request, $return);
        $this->returns->reject($model, $request->validate(['note' => ['required', 'string', 'max:255']])['note']);

        return $this->respond($model);
    }

    public function receive(Request $request, string $return): ReturnResource
    {
        $model = $this->find($request, $return);
        $this->returns->markReceived($model, $request->boolean('restock', true));

        return $this->respond($model);
    }

    public function replace(Request $request, string $return): ReturnResource
    {
        $data = $request->validate(['courier' => ['required', 'string', 'max:100'], 'tracking_number' => ['required', 'string', 'max:100']]);
        $model = $this->find($request, $return);
        $this->returns->shipReplacement($model, $data['courier'], $data['tracking_number']);

        return $this->respond($model);
    }

    private function respond(ReturnRequest $return): ReturnResource
    {
        return new ReturnResource($return->fresh(['items.orderItem', 'subOrder']));
    }

    private function query(Request $request)
    {
        return ReturnRequest::whereHas('subOrder', fn ($q) => $q->where('store_id', $this->currentStore($request)->id));
    }

    private function find(Request $request, string $number): ReturnRequest
    {
        return $this->query($request)->where('number', $number)->firstOrFail();
    }
}
