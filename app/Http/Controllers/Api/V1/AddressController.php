<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Account\AddressController as WebAddresses;
use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Support\Rules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($request->user()->addresses);
    }

    public function store(Request $request): JsonResponse
    {
        return (new AddressResource(WebAddresses::createFrom($request, $request->user())))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $address): AddressResource
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $model->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', Rules::MOBILE],
            ...collect(Rules::address())->map(fn ($rules) => ['sometimes', ...$rules])->all(),
        ], Rules::messages()));

        if ($request->boolean('is_default')) {
            DB::transaction(function () use ($request, $model) {
                $request->user()->addresses()->update(['is_default' => false]);
                $model->update(['is_default' => true]);
            });
        }

        return new AddressResource($model->fresh());
    }

    public function destroy(Request $request, int $address): JsonResponse
    {
        $model = $request->user()->addresses()->findOrFail($address);
        $wasDefault = $model->is_default;
        $model->delete();

        if ($wasDefault && $next = $request->user()->addresses()->first()) {
            $next->update(['is_default' => true]);
        }

        return response()->json(null, 204);
    }
}
