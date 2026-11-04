<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Like the web seller area, every lookup goes through the signed-in seller's store.
 */
abstract class SellerApiController extends Controller
{
    protected function currentStore(Request $request): Store
    {
        return $request->user()->store;
    }
}
