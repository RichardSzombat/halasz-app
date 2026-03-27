<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Halasz Worksheet System' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen bg-app">
            <header class="sticky top-0 z-40 border-b border-white/5 bg-panel/90 backdrop-blur-xl">
                <div class="mx-auto flex w-full max-w-[1320px] items-center justify-between px-6 py-4">
                    <a href="{{ route('worksheets.index') }}" class="brand-mark">HalaszApp</a>

                    <div class="flex items-center gap-3">
                        @yield('headerActions')
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1320px] px-5 py-6 sm:px-6 sm:py-8 lg:px-8">
                @include('worksheets.partials.flash')
                @yield('content')
            </main>
        </div>
    </body>
</html>
