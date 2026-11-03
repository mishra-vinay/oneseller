<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\Role;
use App\Enums\SubOrderStatus;
use App\Http\Resources\SellerSubOrderResource;
use App\Models\SubOrder;
use App\Services\Fulfilment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends SellerApiController
{
    public function __construct(private Fulfilment $fulfilment) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = SubOrderStatus::tryFrom((string) $request->query('status'));

        return SellerSubOrderResource::collection(
            $this->currentStore($request)->subOrders()->where('status', '!=', SubOrderStatus::PendingPayment)
                ->with(['items', 'order'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(25)
        );
    }

    public function show(Request $request, int $subOrder): SellerSubOrderResource
    {
        return new SellerSubOrderResource($this->find($request, $subOrder));
    }

    public function accept(Request $request, int $subOrder): SellerSubOrderResource
    {
        $model = $this->find($request, $subOrder);
        $this->fulfilment->accept($model);

        return new SellerSubOrderResource($model->fresh(['items', 'order']));
    }

    public function ship(Request $request, int $subOrder): SellerSubOrderResource
    {
        $data = $request->validate(['courier' => ['required', 'string', 'max:100'], 'tracking_number' => ['required', 'string', 'max:100']]);
        $model = $this->find($request, $subOrder);
        $this->fulfilment->ship($model, $data['courier'], $data['tracking_number']);

        return new SellerSubOrderResource($model->fresh(['items', 'order']));
    }

    public function deliver(Request $request, int $subOrder): SellerSubOrderResource
    {
        $model = $this->find($request, $subOrder);
        $this->fulfilment->deliver($model);

        return new SellerSubOrderResource($model->fresh(['items', 'order']));
    }

    public function cancel(Request $request, int $subOrder): SellerSubOrderResource
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];
        $model = $this->find($request, $subOrder);
        $this->fulfilment->cancel($model, $reason, Role::Seller);

        return new SellerSubOrderResource($model->fresh(['items', 'order']));
    }

    private function find(Request $request, int $id): SubOrder
    {
        return $this->currentStore($request)->subOrders()->where('status', '!=', SubOrderStatus::PendingPayment)->with(['items', 'order'])->findOrFail($id);
    }
}
