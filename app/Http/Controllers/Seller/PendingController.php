<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Holding page shown until an admin approves the store.
 */
class PendingController extends SellerController
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        $store = $this->currentStore($request);

        if ($store->isApproved()) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.pending', ['store' => $store]);
    }
}
