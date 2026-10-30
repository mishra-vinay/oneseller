<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Support\Settings;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'phone', 'address_line1', 'address_line2',
        'city', 'state', 'pincode', 'gstin',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'approved_at' => 'datetime',
            'commission_bps' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function isApproved(): bool
    {
        return $this->status === StoreStatus::Approved;
    }

    /**
     * Commission rate in force today, in basis points.
     */
    public function effectiveCommissionBps(): int
    {
        return $this->commission_bps ?? app(Settings::class)->get('default_commission_bps');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function balance(): int
    {
        return (int) ($this->ledgerEntries()->latest('id')->value('balance_after') ?? 0);
    }

    /**
     * Stores whose products may appear on the storefront.
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('stores.status', StoreStatus::Approved)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'));
    }
}
