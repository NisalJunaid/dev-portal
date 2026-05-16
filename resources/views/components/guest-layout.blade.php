<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Kiel Portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-8 flex flex-col items-center gap-4 text-center">
                <x-application-logo />
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-950">Kiel Developer Portal</h1>
                    <p class="mt-2 text-sm text-slate-500">Sign in to manage tickets, clients, and delivery work.</p>
                </div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-soft">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
