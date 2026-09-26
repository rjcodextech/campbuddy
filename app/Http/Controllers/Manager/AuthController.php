<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Http\Requests\Manager\ManagerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The event manager sign-in — its own page, its own guard. Nobody registers:
 * an admin creates the account.
 */
class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard(EnsureEventManager::GUARD)->check()) {
            return redirect()->route('manager.dashboard');
        }

        return view('manager.login');
    }

    public function store(ManagerLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $manager = Auth::guard(EnsureEventManager::GUARD)->user();
        $manager->forceFill(['last_login_at' => now()])->saveQuietly();

        // Where they were headed when the session ran out — a page of ours, saved by EnsureEventManager.
        return redirect()->to($request->session()->pull('manager.intended', route('manager.dashboard')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard(EnsureEventManager::GUARD)->logout();
        $request->session()->forget(['manager.password', 'manager.intended']);

        // Someone signed in to the admin panel in the same browser stays signed in there.
        if (! Auth::guard('web')->check()) {
            $request->session()->invalidate();
        }

        $request->session()->regenerateToken();

        return redirect()->route('manager.login');
    }
}
