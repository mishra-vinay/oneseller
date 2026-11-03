<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['rating', 'title', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_hidden' => 'boolean', 'seller_replied_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeShown(Builder $query): void
    {
        $query->where('is_hidden', false);
    }

    /**
     * "Asha V." — reviews never show a buyer's full name or email.
     */
    public function authorName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $first = $parts[0] ?? __('Buyer');
        $last = count($parts) > 1 ? ' '.mb_substr(end($parts), 0, 1).'.' : '';

        return $first.$last;
    }
}
