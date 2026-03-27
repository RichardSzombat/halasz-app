@extends('layouts.app', [
    'title' => 'Bejelentkezés - Halasz Worksheet System',
])

@section('content')
    <section class="mx-auto max-w-2xl">
        <article class="panel-shell overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-300/70">Bejelentkezés</p>
                <h1 class="mt-3 text-3xl font-semibold italic tracking-tight text-white">Üdv újra</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">Lépj be a munkalapok és elszámolások kezeléséhez.</p>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5 px-6 py-6">
                @csrf

                <div>
                    <label for="login_email" class="field-label">E-mail cím</label>
                    <input id="login_email" name="login_email" type="email" class="input-dark" value="{{ $loginEmail }}" autocomplete="email" required>
                </div>

                <div>
                    <label for="login_password" class="field-label">Jelszó</label>
                    <input id="login_password" name="login_password" type="password" class="input-dark" autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-primary w-full">Bejelentkezés</button>

                <div class="auth-divider">
                    <span>vagy</span>
                </div>

                <div class="space-y-3 text-center">
                    <p class="text-sm text-slate-400">Nincs még fiókod?</p>
                    <a href="{{ route('register') }}" class="btn-accent w-full">Regisztráció</a>
                </div>
            </form>
        </article>
    </section>
@endsection
