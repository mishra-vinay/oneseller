<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
