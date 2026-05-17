@php
    $menuItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'grid'],
        ['label' => 'Tickets', 'route' => 'tickets.index', 'permission' => 'view tickets', 'icon' => 'ticket'],
        ['label' => 'Kanban', 'route' => 'kanban.index', 'permission' => 'view tickets', 'icon' => 'columns'],
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

        <div x-data="toastCenter" x-cloak class="fixed right-4 top-4 z-[70] w-[calc(100%-2rem)] max-w-sm space-y-3 sm:right-6 sm:top-6" aria-live="polite">
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="rounded-2xl border px-4 py-3 text-sm font-bold shadow-soft backdrop-blur"
                    :class="toneClasses(toast.type)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span x-text="toast.message"></span>
                        <button type="button" class="text-current/60 transition hover:text-current" @click="remove(toast.id)" aria-label="Dismiss notification">×</button>
                    </div>
                </div>
            </template>
        </div>

        <div x-data="confirmModal" x-cloak x-show="open" class="fixed inset-0 z-[80] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" @keydown.escape.window="answer(false)">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" @click="answer(false)"></div>
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-6 opacity-0 scale-95"
                x-transition:enter-end="translate-y-0 opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-y-0 opacity-100 scale-100"
                x-transition:leave-end="translate-y-6 opacity-0 scale-95"
                class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl"
            >
                <p class="text-xs font-black uppercase tracking-[0.25em] text-slate-400">Please confirm</p>
                <h2 class="mt-3 text-2xl font-black text-slate-950" x-text="title"></h2>
                <p class="mt-3 text-sm leading-6 text-slate-600" x-text="message"></p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-black text-slate-600 transition hover:border-slate-300 hover:text-slate-950" @click="answer(false)" x-text="cancelLabel"></button>
                    <button type="button" x-ref="confirmButton" class="rounded-2xl px-5 py-3 text-sm font-black shadow-soft transition" :class="confirmClasses()" @click="answer(true)" x-text="confirmLabel"></button>
                </div>
            </div>
        </div>

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
                        <a href="{{ route($item['route']) }}" @class(['app-shell-link', 'app-shell-link-active' => request()->routeIs($item['route']) || ($item['route'] === 'kanban.index' && request()->routeIs('kanban.*'))])>
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
@if (session('status'))
    <script>window.addEventListener('DOMContentLoaded', () => window.Kiel?.toast(@js(session('status')), 'success'));</script>
@endif
</body>
</html>
