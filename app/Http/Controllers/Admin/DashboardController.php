<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $placed = fn () => Order::whereNotIn('status', [OrderStatus::PendingPayment, OrderStatus::Cancelled]);

        $from = today()->subDays(6);
        $byDay = $placed()->where('placed_at', '>=', $from)->get(['placed_at'])
            ->countBy(fn ($o) => $o->placed_at->toDateString());

        $chart = collect(range(0, 6))->map(function ($offset) use ($from, $byDay) {
            $day = $from->copy()->addDays($offset);

            return ['label' => $day->format('D'), 'date' => $day->format('d M'), 'count' => $byDay->get($day->toDateString(), 0)];
        });

        return view('admin.dashboard', [
            'ordersToday' => $placed()->where('placed_at', '>=', today())->count(),
            'salesToday' => (int) $placed()->where('placed_at', '>=', today())->sum('grand_total'),
            'pendingProducts' => Product::where('status', ProductStatus::Pending)->count(),
            'pendingSellers' => Store::where('status', StoreStatus::Pending)->count(),
            'activeSellers' => Store::where('status', StoreStatus::Approved)->count(),
            'buyers' => User::role(Role::Buyer)->count(),
            'chart' => $chart,
            'chartMax' => max(1, $chart->max('count')),
            'activities' => Activity::orderByDesc('id')->limit(8)->get(),
        ]);
    }
}
