<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.activity', [
            'activities' => Activity::with('actor')
                ->when($request->query('event'), fn ($q, $prefix) => $q->where('event', 'like', $prefix.'.%'))
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
