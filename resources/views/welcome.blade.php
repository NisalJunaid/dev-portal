<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Kiel Portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white">
    <main class="flex min-h-screen items-center justify-center px-6 py-16">
        <div class="max-w-3xl text-center">
            <div class="mx-auto mb-8 flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-500 text-2xl font-black shadow-soft">K</div>
            <p class="text-sm font-bold uppercase tracking-[0.35em] text-indigo-300">Kiel Developer Portal</p>
            <h1 class="mt-5 text-5xl font-black tracking-tight sm:text-6xl">A focused workspace for software delivery.</h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">Authenticate with your seeded role to access a responsive Asana-inspired dashboard with role-aware navigation and client-scoped authorization.</p>
            <a href="{{ route('login') }}" class="mt-10 inline-flex rounded-2xl bg-white px-6 py-3 text-sm font-black text-slate-950 shadow-soft transition hover:bg-indigo-100">Sign in</a>
        </div>
    </main>
</body>
</html>
