<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends SellerController
{
    public function __invoke(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('seller.earnings', [
            'balance' => $store->balance(),
            'entries' => $store->ledgerEntries()->with('subOrder')->orderByDesc('id')->paginate(50),
        ]);
    }
}
