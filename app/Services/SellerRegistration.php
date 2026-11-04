<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StoreStatus;
use App\Models\Activity;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewSellerAlert;
use App\Notifications\SellerRegistrationReceived;
use App\Support\AdminNotifier;
use App\Support\Settings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerRegistration
{
    public function __construct(private Settings $settings) {}

    /**
     * Creates the seller's login and store in one step.
     *
     * @param  array<string, mixed>  $account  name, email, phone, password
     * @param  array<string, mixed>  $store  store fields
     */
    public function register(array $account, array $store): Store
    {
        $autoApprove = $this->settings->get('auto_approve_sellers');

        $created = DB::transaction(function () use ($account, $store, $autoApprove) {
            $user = new User($account);
            $user->role = Role::Seller;
            $user->save();

            $model = new Store($store);
            $model->user()->associate($user);
            $model->slug = self::uniqueSlug($store['name']);
            $model->status = $autoApprove ? StoreStatus::Approved : StoreStatus::Pending;
            $model->approved_at = $autoApprove ? now() : null;
            $model->save();

            return $model;
        });

        event(new Registered($created->user));
        Activity::record('seller.registered', "New seller registered: {$created->name}", $created);

        $created->user->notify(new SellerRegistrationReceived($created));
        AdminNotifier::send(new NewSellerAlert($created));

        return $created;
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        for ($i = 2; Store::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
