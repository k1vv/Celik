<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', Rule::in(['teacher', 'student'])],
        ]);

        if (! Auth::attempt(Arr::only($credentials, ['email', 'password']), remember: true)) {
            return back()->withErrors([
                'email' => 'Those credentials do not match our records.',
            ])->onlyInput('email');
        }

        if (isset($credentials['role']) && Auth::user()->role !== $credentials['role']) {
            Auth::logout();

            return back()->withErrors([
                'email' => "These credentials do not belong to a {$credentials['role']} account.",
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return $this->redirectForRole();
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function redirectForRole(): RedirectResponse
    {
        return redirect()->route(Auth::user()->homeRouteName());
    }
}
