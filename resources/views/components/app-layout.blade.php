@php
    $menuItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'grid'],
        ['label' => 'Tickets', 'route' => 'tickets.index', 'permission' => 'view tickets', 'icon' => 'ticket'],
        ['label' => 'Bugs', 'route' => 'bugs.index', 'permission' => 'view bugs', 'icon' => 'bug'],
        ['label' => 'Features', 'route' => 'features.index', 'permission' => 'view features', 'icon' => 'sparkles'],
        ['label' => 'Sprints', 'route' => 'sprints.index', 'permission' => 'view sprints', 'icon' => 'calendar'],
        ['label' => 'Timeline', 'route' => 'timeline.index', 'permission' => 'view timeline', 'icon' => 'timeline'],
        ['label' => 'Reports', 'route' => 'reports.index', 'permission' => 'view reports', 'icon' => 'chart'],
        ['label' => 'Clients', 'route' => 'clients.index', 'permission' => 'view clients', 'icon' => 'building'],
        ['label' => 'Software', 'route' => 'softwares.index', 'permission' => 'view software', 'icon' => 'cube'],
        ['label' => 'Settings', 'route' => 'settings.index', 'permission' => 'view settings', 'icon' => 'cog'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Kiel Portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-50">
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="sidebarOpen = false"></div>

        <aside class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full border-r border-slate-200 bg-slate-100/95 px-4 py-5 transition duration-200 ease-out lg:translate-x-0" :class="{ 'translate-x-0': sidebarOpen }">
            <div class="flex items-center gap-3 px-2">
                <x-application-logo class="h-11 w-11" />
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.25em] text-indigo-600">Kiel</p>
                    <p class="text-lg font-black text-slate-950">Dev Portal</p>
                </div>
            </div>

            <nav class="mt-8 space-y-1">
                @foreach ($menuItems as $item)
                    @if ($item['permission'] === null || auth()->user()->can($item['permission']))
                        <a href="{{ route($item['route']) }}" @class(['app-shell-link', 'app-shell-link-active' => request()->routeIs($item['route']) || request()->routeIs(str($item['route'])->before('.index').'.*')])>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-200/80 text-xs font-black text-slate-500">{{ mb_substr($item['label'], 0, 1) }}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-slate-50/85 backdrop-blur">
                <div class="flex h-20 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-4">
                        <button type="button" class="rounded-2xl border border-slate-200 bg-white p-2 text-slate-600 shadow-sm lg:hidden" @click="sidebarOpen = true">
                            <span class="sr-only">Open sidebar</span>
                            ☰
                        </button>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.25em] text-slate-400">Workspace</p>
                            <h1 class="text-xl font-black text-slate-950">{{ $header ?? 'Dashboard' }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="hidden text-right sm:block">
                            <p class="text-sm font-bold text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->roles->pluck('name')->map(fn ($role) => str($role)->headline())->join(', ') }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600">Log out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
