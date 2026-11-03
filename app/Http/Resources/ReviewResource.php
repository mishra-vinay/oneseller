<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Review */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'author' => $this->authorName(),
            'seller_reply' => $this->seller_reply,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
