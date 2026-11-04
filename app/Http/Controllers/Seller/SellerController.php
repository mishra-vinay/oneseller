<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

/**
 * Base for seller-area controllers. Every lookup goes through currentStore(),
 * e.g. currentStore()->products()->findOrFail($id), so a seller can never reach
 * another store's records, even by editing the URL: they get a 404.
 */
abstract class SellerController extends Controller
{
    protected function currentStore(Request $request): Store
    {
        return $request->user()->store;
    }
}
