<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerController extends Controller
{
    public function index(Request $request): View
    {
        $counted = fn ($q) => $q->whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled]);

        $buyers = User::role(Role::Buyer)
            ->withCount(['orders' => $counted])
            ->withSum(['orders as total_spent' => $counted], 'grand_total')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.buyers.index', ['buyers' => $buyers]);
    }

    public function show(User $buyer): View
    {
        abort_unless($buyer->role === Role::Buyer, 404);

        return view('admin.buyers.show', [
            'buyer' => $buyer,
            'orders' => $buyer->orders()->with('subOrders.store')->latest()->paginate(20),
        ]);
    }

    public function deactivate(User $buyer): RedirectResponse
    {
        return $this->setStatus($buyer, AccountStatus::Deactivated);
    }

    public function reactivate(User $buyer): RedirectResponse
    {
        return $this->setStatus($buyer, AccountStatus::Active);
    }

    private function setStatus(User $buyer, AccountStatus $status): RedirectResponse
    {
        abort_unless($buyer->role === Role::Buyer, 404);

        $buyer->forceFill(['status' => $status])->save();
        Activity::record('buyer.'.$status->value, "Buyer {$buyer->email} {$status->value}", $buyer);

        return back()->with('status', $status === AccountStatus::Active ? __('Buyer reactivated.') : __('Buyer deactivated. They are logged out on their next click.'));
    }
}
