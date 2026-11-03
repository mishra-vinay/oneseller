<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Enums\ReturnType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    public const REASONS = [
        'damaged' => 'Arrived damaged',
        'wrong_item' => 'Wrong item sent',
        'not_as_described' => 'Not as described',
        'size_fit' => 'Size or fit',
        'quality' => 'Quality not as expected',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ReturnType::class,
            'status' => ReturnStatus::class,
            'refund_amount' => 'integer',
            'restocked' => 'boolean',
            'decided_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
