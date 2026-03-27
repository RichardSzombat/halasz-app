@if (session('status'))
    <div
        x-data="{ show: true }"
        x-cloak
        x-show="show"
        x-init="setTimeout(() => show = false, 3600)"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
        class="pointer-events-none fixed inset-x-4 top-4 z-[70] flex justify-center sm:inset-x-auto sm:right-5 sm:top-5 sm:justify-end"
    >
        <div class="pointer-events-auto w-full max-w-sm rounded-2xl border border-emerald-400/30 bg-[#162638]/95 px-4 py-3 text-sm font-medium text-emerald-100 shadow-[0_18px_40px_rgba(0,0,0,0.35)] backdrop-blur-xl">
            {{ session('status') }}
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
        <p class="font-semibold">Kérlek ellenőrizd az űrlap mezőit.</p>
        <ul class="mt-2 space-y-1 text-rose-50/90">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
