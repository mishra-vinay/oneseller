<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The app registers its FCM token after signing in, and removes it when signing out.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
        ]);

        // A device that changes hands moves to the user now signed in on it.
        $device = DeviceToken::firstOrNew(['token' => $data['token']]);
        $device->user()->associate($request->user());
        $device->fill(['platform' => $data['platform'], 'last_used_at' => now()])->save();

        return response()->json(['message' => __('Device registered for notifications.')], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return response()->json(null, 204);
    }
}
