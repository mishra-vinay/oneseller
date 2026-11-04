<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * The admin activity feed: significant marketplace events.
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['actor_id', 'event', 'description'];

    public static function record(string $event, string $description, ?Model $subject = null): self
    {
        $activity = new self([
            'actor_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
        ]);
        $activity->subject()->associate($subject);
        $activity->save();

        return $activity;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
