<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Coupon;
use App\Models\Store;
use App\Services\CouponService;
use App\Support\Money;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Platform-funded discount codes: sellers are always credited the full sale price.
 */
class CouponController extends Controller
{
    public function index(): View
    {
        $live = fn ($q) => $q->whereHas('order', fn ($o) => $o->where('status', '!=', OrderStatus::Cancelled));

        return view('admin.coupons.index', [
            'coupons' => Coupon::with('store')
                ->withCount(['redemptions as uses' => $live])
                ->withSum(['redemptions as discount_given' => $live], 'discount')
                ->latest()
                ->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => CouponType::Percent, 'is_active' => true, 'per_user_limit' => 1]), 'stores' => $this->stores()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = new Coupon;
        $this->save($request, $coupon);
        Activity::record('coupon.created', "Coupon {$coupon->code} created", $coupon);

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon :code created.', ['code' => $coupon->code]));
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon, 'stores' => $this->stores()]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $this->save($request, $coupon);

        return redirect()->route('admin.coupons.index')->with('status', __('Coupon :code saved.', ['code' => $coupon->code]));
    }

    private function save(Request $request, Coupon $coupon): void
    {
        $request->merge(['code' => CouponService::normalise($request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', $request->input('type') === 'percent' ? 'regex:/^\d{1,2}(\.\d{1,2})?$|^100$/' : Rules::RUPEES],
            'min_order_rupees' => ['nullable', Rules::RUPEES],
            'max_discount_rupees' => ['nullable', Rules::RUPEES],
            'store_id' => ['nullable', Rule::exists('stores', 'id')],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [
            'value.regex' => $request->input('type') === 'percent' ? __('Enter a percentage from 0.01 to 100.') : __('Enter an amount in rupees, e.g. 150.'),
            '*.regex' => __('Enter an amount in rupees, e.g. 150.'),
        ]);

        $value = Money::fromRupees($data['value']);

        if ($value === 0) {
            throw ValidationException::withMessages(['value' => __('The discount must be more than zero.')]);
        }

        $coupon->fill([
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            // Percent with two decimals maps onto basis points the same way rupees map onto paise.
            'value' => $value,
            'min_order_paise' => filled($data['min_order_rupees'] ?? null) ? Money::fromRupees($data['min_order_rupees']) : null,
            'max_discount_paise' => filled($data['max_discount_rupees'] ?? null) ? Money::fromRupees($data['max_discount_rupees']) : null,
            'store_id' => $data['store_id'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_user_limit' => $data['per_user_limit'] ?? null,
            'starts_at' => filled($data['starts_at'] ?? null) ? Carbon::parse($data['starts_at'])->startOfDay() : null,
            'ends_at' => filled($data['ends_at'] ?? null) ? Carbon::parse($data['ends_at'])->endOfDay() : null,
            'is_active' => $request->boolean('is_active'),
        ])->save();
    }

    private function stores()
    {
        return Store::orderBy('name')->pluck('name', 'id');
    }
}
