<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Page extends Model
{
    public const TYPE_PAGE = 'page';

    public const TYPE_POST = 'post';

    protected $fillable = ['type', 'title', 'slug', 'excerpt', 'body', 'show_in_footer', 'is_published', 'published_at'];

    protected function casts(): array
    {
        return ['show_in_footer' => 'boolean', 'is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * Markdown to HTML. Raw HTML in the source is stripped and unsafe links are dropped.
     */
    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    public function url(): string
    {
        return $this->type === self::TYPE_POST ? route('blog.show', $this->slug) : route('pages.show', $this->slug);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }
}
