<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        return view('pages.page', ['page' => Page::published()->where('type', Page::TYPE_PAGE)->where('slug', $slug)->firstOrFail()]);
    }

    public function blog(): View
    {
        return view('pages.blog', ['posts' => Page::published()->where('type', Page::TYPE_POST)->latest('published_at')->paginate(12)]);
    }

    public function post(string $slug): View
    {
        return view('pages.post', ['page' => Page::published()->where('type', Page::TYPE_POST)->where('slug', $slug)->firstOrFail()]);
    }

    /**
     * One-click unsubscribe from cart reminder emails (signed link in the email).
     */
    public function unsubscribeCartReminders(User $user): View
    {
        $user->forceFill(['cart_reminders' => false])->save();

        return view('pages.unsubscribed');
    }
}
