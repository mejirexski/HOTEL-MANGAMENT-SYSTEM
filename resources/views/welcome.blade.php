<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blue Diamond Hotel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- text decoration --}}
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    {{-- favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('image/BDHOTEL.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .font-brand { font-family: 'Cinzel', serif; }
        body { font-family: 'Inter', sans-serif; }
        .silver-text {
            background-image: linear-gradient(to bottom, #f4f1ed, #c9c3bd 55%, #8c8782);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        /* Put your photo at public/image/hero.jpg. If it is missing, the navy gradient shows instead. */
        .hero-bg {
            background-image:
                linear-gradient(to right, rgba(5,13,36,.92) 0%, rgba(5,13,36,.65) 55%, rgba(5,13,36,.35) 100%),
                url('{{ asset('image/hero.jpg') }}'),
                linear-gradient(135deg, #050d24, #0d2a6b);
            background-size: cover;
            background-position: center;
        }
    </style>

</head>
<body class="bg-[#050d24] text-slate-200 antialiased">

    {{-- NAVBAR --}}
    <header class="absolute inset-x-0 top-0 z-20">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5">

            {{-- logo --}}
            <div class="flex items-center gap-10">
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('image/BDHOTEL.png') }}"
                         alt="Blue Diamond Hotel logo"
                         class="h-12 w-12 rounded-lg object-cover">
                    <span class="hidden flex-col items-center leading-none sm:flex">
                        <span class="font-brand silver-text text-lg font-semibold uppercase tracking-[0.12em]">Blue Diamond</span>
                        <span class="font-brand mt-1 text-[9px] uppercase tracking-[0.55em] text-[#c9c3bd]">Hotel</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-7 text-sm text-slate-300 md:flex">
                    <a href="#rooms" class="hover:text-white">Rooms</a>
                    <a href="#about" class="hover:text-white">About Us</a>
                    <a href="#facilities" class="hover:text-white">Facilities</a>
                    <a href="#contact" class="hover:text-white">Contact Us</a>
                </nav>
            </div>

            <div class="flex items-center gap-5">
                {{-- Replace the # with your real social links --}}
                <a href="#" aria-label="Facebook" class="text-slate-300 hover:text-white">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z"/></svg>
                </a>
                <a href="#" aria-label="Instagram" class="text-slate-300 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                </a>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}"
                           class="rounded-lg border border-white/80 px-5 py-2 text-sm font-medium text-white hover:bg-white hover:text-[#050d24]">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="rounded-lg border border-white/80 px-5 py-2 text-sm font-medium text-white hover:bg-white hover:text-[#050d24]">
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                               class="rounded-lg border border-white/80 px-5 py-2 text-sm font-medium text-white hover:bg-white hover:text-[#050d24]">
                                Register
                            </a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <section class="hero-bg flex min-h-screen items-center">
        <div class="mx-auto w-full max-w-7xl px-6 pt-24">
            <p class="text-sm font-semibold uppercase text-white">Welcome to</p>
            <h1 class="font-brand silver-text mt-4 max-w-3xl text-5xl font-bold leading-tight sm:text-7xl">
                Blue Diamond Hotel
            </h1>
            <p class="mt-6 max-w-xl text-base font-semibold text-white">
                Comfort, service and a stay you will remember, at a price that respects your budget.
            </p>

            @auth
                <a href="{{ url('/dashboard') }}" class="mt-8 inline-block rounded-lg bg-white px-7 py-3 text-sm font-semibold text-[#050d24] hover:bg-slate-200">Make Reservation</a>
            @else
                <a href="{{ route('login') }}" class="mt-8 inline-block rounded-lg bg-white px-7 py-3 text-sm font-semibold text-[#050d24] hover:bg-slate-200">Make Reservation</a>
            @endauth
        </div>
    </section>

    {{-- ROOMS --}}
    <section id="rooms" class="mx-auto max-w-7xl px-6 py-24">
        <h2 class="font-brand silver-text text-3xl font-semibold">Rooms</h2>
        <p class="mt-3 max-w-xl text-slate-400">Pick a room that fits your stay. Rates and availability are shown when you book.</p>

        {{-- Replace with a loop over your Room model when ready: @foreach($rooms as $room) --}}
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach (['Standard Room', 'Deluxe Room', 'Executive Suite'] as $type)
                <div class="rounded-xl border border-white/10 bg-white/5 p-6">
                    <div class="aspect-[4/3] rounded-lg bg-gradient-to-br from-[#0d2a6b] to-[#050d24]"></div>
                    <h3 class="font-brand mt-5 text-lg font-semibold text-white">{{ $type }}</h3>
                    <p class="mt-2 text-sm text-slate-400">Room details and nightly rate go here.</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ABOUT: image left, text right (like Pinnacle) --}}
    <section id="about" class="border-t border-white/10 bg-[#07122e]">
        <div class="mx-auto grid max-w-5xl items-center gap-10 px-6 py-24 md:grid-cols-2">
            {{-- Save a photo of the hotel at public/image/about.jpg. Navy gradient shows if it is missing. --}}
            <div class="aspect-[3/4] rounded-xl bg-gradient-to-br from-[#0d2a6b] to-[#050d24] bg-cover bg-center"
                 style="background-image: url('{{ asset('image/about.jpg') }}'), linear-gradient(135deg, #0d2a6b, #050d24);"></div>

            <div>
                <h2 class="font-brand silver-text text-3xl font-semibold leading-snug">
                    Located within the heart of Asaba, Delta State.
                </h2>
                <p class="mt-5 text-sm font-semibold text-slate-200">
                    Write one short sentence introducing the hotel and the group behind it.
                </p>
                <p class="mt-3 text-sm leading-relaxed text-slate-400">
                    Write two or three sentences about where the hotel is, what is nearby, and how far it is from the airport and the business district.
                </p>
                <a href="#rooms" class="mt-6 inline-block rounded-lg border border-blue-400 px-4 py-2 text-sm font-medium text-blue-300 hover:bg-blue-500/10">
                    Discover More
                </a>
            </div>
        </div>
    </section>

    {{-- STATS (replace with your real numbers) --}}
    <section class="border-y border-white/10 bg-[#07122e]">
        <div class="mx-auto grid max-w-5xl grid-cols-2 gap-8 px-6 py-10 text-center md:grid-cols-4">
            @foreach ([['0', 'Years of service'], ['0', 'Sq. meter area'], ['0', 'Rooms'], ['0', 'Happy visitors']] as [$num, $label])
                <div>
                    <p class="font-brand silver-text text-3xl font-semibold">{{ $num }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- FACILITIES --}}
    <section id="facilities" class="mx-auto max-w-5xl px-6 py-24 text-center">
        <h2 class="font-brand silver-text text-3xl font-semibold">Facilities</h2>
        <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400">Write one or two sentences about what the hotel offers.</p>

        @php
            // 'letter' shows a letter badge, 'path' draws an icon. Add or remove items freely.
            $facilities = [
                ['label' => 'Free Parking',      'letter' => 'P'],
                ['label' => 'Free Wi-Fi',        'path' => 'M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z'],
                ['label' => '24-hour Front Desk','path' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label' => 'Central Location',  'path' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
            ];
        @endphp

        <ul class="mt-10 grid grid-cols-2 gap-8 md:grid-cols-4">
            @foreach ($facilities as $f)
                <li class="flex flex-col items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-600/20 text-blue-400">
                        @isset($f['letter'])
                            <span class="font-brand text-lg font-bold">{{ $f['letter'] }}</span>
                        @else
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $f['path'] }}"/></svg>
                        @endisset
                    </span>
                    <span class="text-sm font-medium text-slate-200">{{ $f['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- HOW TO REACH + MAP --}}
    @php
        // Put your real hotel address here. The map and the Get Direction button both use it.
        $hotelAddress = 'Your hotel street address, Asaba, Delta State, Nigeria';
        $q = urlencode($hotelAddress);
    @endphp
    <section id="contact" class="border-t border-white/10 bg-[#07122e]">
        <div class="mx-auto max-w-5xl px-6 py-24">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="font-brand silver-text text-3xl font-semibold">How to Reach</h2>
                    <p class="mt-2 text-sm text-slate-400">{{ $hotelAddress }}</p>
                </div>
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $q }}"
                   target="_blank" rel="noopener"
                   class="rounded-lg border border-blue-400 px-4 py-2 text-sm font-medium text-blue-300 hover:bg-blue-500/10">
                    Get Direction
                </a>
            </div>

            <div class="mt-8 overflow-hidden rounded-xl border border-white/10">
                <iframe
                    title="Blue Diamond Hotel on the map"
                    src="https://www.google.com/maps?q={{ $q }}&output=embed"
                    class="h-80 w-full md:h-96"
                    style="border:0"
                    loading="lazy"
                    allowfullscreen
                    referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>

            <p class="mt-6 text-sm text-slate-400">Phone: your number &nbsp;|&nbsp; Email: your email</p>
        </div>
    </section>

    <footer class="border-t border-white/10 py-8 text-center text-sm text-slate-500">
        &copy; {{ date('Y') }} Blue Diamond Hotel. All rights reserved.
    </footer>

</body>
</html>