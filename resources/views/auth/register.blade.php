@extends('layouts.app', [
    'title' => 'Regisztráció - Halasz Worksheet System',
])

@section('content')
    <section class="mx-auto max-w-2xl">
        <article class="panel-shell overflow-hidden">
            <div class="border-b border-white/10 px-6 py-5">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-violet-300/70">Regisztráció</p>
                <h1 class="mt-3 text-3xl font-semibold italic tracking-tight text-white">Új fiók létrehozása</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">Add meg az adataidat, és a rendszer azonnal be is léptet.</p>
            </div>

            <form method="POST" action="{{ route('register.store') }}" class="space-y-5 px-6 py-6">
                @csrf

                <div>
                    <label for="name" class="field-label">Név</label>
                    <input id="name" name="name" type="text" class="input-dark" value="{{ $registerName }}" autocomplete="name" required>
                </div>

                <div>
                    <label for="email" class="field-label">E-mail cím</label>
                    <input id="email" name="email" type="email" class="input-dark" value="{{ $registerEmail }}" autocomplete="email" required>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="field-label">Jelszó</label>
                        <input id="password" name="password" type="password" class="input-dark" autocomplete="new-password" required>
                    </div>

                    <div>
                        <label for="password_confirmation" class="field-label">Jelszó újra</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input-dark" autocomplete="new-password" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full">Regisztráció</button>

                <div class="auth-divider">
                    <span>vagy</span>
                </div>

                <div class="space-y-3 text-center">
                    <p class="text-sm text-slate-400">Már van fiókod?</p>
                    <a href="{{ route('login') }}" class="btn-accent w-full">Bejelentkezés</a>
                </div>
            </form>
        </article>
    </section>
@endsection
