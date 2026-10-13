<?php

namespace App\Http\Controllers;

use App\Models\OrganizerPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The store app registers (and, on sign-out, removes) the push token of the
 * admin or event manager signed in on it. Session-authenticated like the
 * rest of the panel; a token belongs to one person at a time, so signing in
 * as someone else on the same phone moves it.
 */
class AppPushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'in:android,ios'],
            'token' => ['required', 'string', 'min:20', 'max:512', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
            'app_version' => ['nullable', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/'],
        ]);
        [$role, $user] = $this->who($request);

        // A handful per person covers a phone and a tablet and keeps the table bounded.
        $isNew = ! OrganizerPushToken::where('token', $data['token'])->exists();
        abort_if($isNew && OrganizerPushToken::where('role', $role)->where('user_id', $user->getKey())->count() >= 5, 422, 'Too many devices.');

        OrganizerPushToken::updateOrCreate(
            ['token' => $data['token']],
            ['role' => $role, 'user_id' => $user->getKey(), 'platform' => $data['platform'], 'app_version' => $data['app_version'] ?? null]
        );

        return response()->json(null, 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:512']]);
        [$role, $user] = $this->who($request);

        OrganizerPushToken::where('token', $data['token'])->where('role', $role)->where('user_id', $user->getKey())->delete();

        return response()->json(null, 204);
    }

    /** @return array{0: string, 1: \Illuminate\Contracts\Auth\Authenticatable} */
    private function who(Request $request): array
    {
        return $request->routeIs('manager.*')
            ? ['manager', $request->user('manager')]
            : ['admin', $request->user()];
    }
}
