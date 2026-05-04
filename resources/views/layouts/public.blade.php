<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="min-h-screen bg-[#f6f1e8] text-zinc-900 antialiased">
        <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[32rem] bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.18),_transparent_58%),linear-gradient(180deg,_#fff7ed_0%,_#f6f1e8_70%)]"></div>
        <div class="pointer-events-none fixed inset-y-0 right-0 -z-10 hidden w-1/3 bg-[radial-gradient(circle_at_center,_rgba(20,184,166,0.12),_transparent_62%)] lg:block"></div>

        <header class="sticky top-0 z-20 border-b border-black/5 bg-white/75 backdrop-blur-xl">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
                    <span class="flex size-11 items-center justify-center rounded-2xl bg-[#1f1d16] text-sm font-semibold uppercase tracking-[0.28em] text-[#f7a24e]">
                        DT
                    </span>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.32em] text-[#b45309]">DISCTRACK</div>
                        <div class="text-sm text-zinc-600">Public tournament board</div>
                    </div>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium text-zinc-600">
                    <a
                        href="{{ route('tournaments.index') }}"
                        class="rounded-full px-4 py-2 transition hover:bg-black/5 hover:text-zinc-900"
                        wire:navigate
                    >
                        Browse
                    </a>

                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="rounded-full bg-[#1f1d16] px-4 py-2 text-white transition hover:bg-black"
                            wire:navigate
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="rounded-full border border-black/10 px-4 py-2 transition hover:border-black/20 hover:bg-white"
                            wire:navigate
                        >
                            Login
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="rounded-full bg-[#1f1d16] px-4 py-2 text-white transition hover:bg-black"
                                wire:navigate
                            >
                                Captain Sign Up
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @yield('content')
        </main>
        @fluxScripts
        <script data-navigate-once>
            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (!(form instanceof HTMLFormElement) || !form.matches('[data-livewire-navigate-form]')) {
                    return;
                }

                const navigate = window.Livewire?.navigate;

                if (typeof navigate !== 'function') {
                    return;
                }

                event.preventDefault();

                const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
                const formData = new FormData(form);

                url.search = '';

                for (const [key, value] of formData.entries()) {
                    if (typeof value !== 'string' || value === '') {
                        continue;
                    }

                    url.searchParams.append(key, value);
                }

                navigate(url.toString());
            });
        </script>
    </body>
</html>
