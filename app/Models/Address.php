<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = ['name', 'phone', 'address_line1', 'address_line2', 'city', 'state', 'pincode', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oneLine(): string
    {
        return collect([$this->address_line1, $this->address_line2, $this->city, $this->state.' '.$this->pincode])->filter()->implode(', ');
    }
}
