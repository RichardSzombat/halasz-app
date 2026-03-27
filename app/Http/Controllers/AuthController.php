<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('worksheets.index');
        }

        return view('auth.login', [
            'title' => 'Bejelentkezés - Halasz Worksheet System',
            'loginEmail' => old('login_email'),
        ]);
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('worksheets.index');
        }

        return view('auth.register', [
            'title' => 'Regisztráció - Halasz Worksheet System',
            'registerName' => old('name'),
            'registerEmail' => old('email'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_email' => ['required', 'email'],
            'login_password' => ['required', 'string'],
        ], [], [
            'login_email' => 'e-mail cím',
            'login_password' => 'jelszó',
        ]);

        if (! Auth::attempt([
            'email' => $credentials['login_email'],
            'password' => $credentials['login_password'],
        ], $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'login_email' => 'A megadott e-mail cím vagy jelszó nem megfelelő.',
                ])
                ->onlyInput('login_email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('worksheets.index'))
            ->with('status', 'Sikeres bejelentkezés.');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'name' => 'név',
            'email' => 'e-mail cím',
            'password' => 'jelszó',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('worksheets.index')
            ->with('status', 'Sikeres regisztráció.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Sikeres kijelentkezés.');
    }
}
