@php
    $tasksActive = request()->routeIs('tasks.*')
        || request()->routeIs('tickets.*')
        || request()->routeIs('kanban.*')
        || request()->routeIs('timeline.*')
        || request()->routeIs('bugs.*')
        || request()->routeIs('features.*');

    $menuItems = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'permission' => null, 'icon' => 'D', 'active' => request()->routeIs('dashboard')],
        ['label' => 'Tasks', 'route' => 'tasks.index', 'permission' => 'view tickets', 'icon' => 'T', 'active' => $tasksActive],
        ['label' => 'Sprints', 'route' => 'sprints.index', 'permission' => 'view sprints', 'icon' => 'S', 'active' => request()->routeIs('sprints.*')],
        ['label' => 'Reports', 'route' => 'reports.index', 'permission' => 'view reports', 'icon' => 'R', 'active' => request()->routeIs('reports.*')],
        ['label' => 'Clients', 'route' => 'clients.index', 'permission' => 'view clients', 'icon' => 'C', 'active' => request()->routeIs('clients.*')],
        ['label' => 'Software', 'route' => 'softwares.index', 'permission' => 'view software', 'icon' => 'SW', 'active' => request()->routeIs('softwares.*')],
        ['label' => 'Settings', 'route' => 'settings.index', 'permission' => 'view settings', 'icon' => '⚙', 'active' => request()->routeIs('settings.*')],
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
    <div
        x-data="appShell()"
        x-init="initShell()"
        class="min-h-screen bg-slate-50 app-shell"
        style="--sidebar-width: 288px; --content-left: 288px;"
    >
        <div x-cloak x-show="pageLeaving" x-transition.opacity.duration.120ms class="fixed inset-0 z-[200] pointer-events-none bg-slate-50/60 backdrop-blur-[1px]"></div>
        <div x-cloak x-show="pageLeaving" class="fixed left-0 top-0 z-[210] h-0.5 w-full overflow-hidden bg-transparent">
            <div class="h-full w-1/3 animate-app-progress rounded-r-full bg-indigo-500"></div>
        </div>
        <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden" @click="sidebarOpen = false"></div>

        <aside
            class="app-shell-sidebar fixed inset-y-0 left-0 z-40 -translate-x-full border-r border-slate-200 bg-slate-100/95 px-4 py-5 transition duration-200 ease-out lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex items-center justify-between px-2">
                <div class="flex items-center gap-3 overflow-hidden">
                    <x-application-logo class="h-11 w-11 shrink-0" />
                    <div x-show="!sidebarCollapsed" x-transition.opacity>
                        <p class="text-sm font-black uppercase tracking-[0.25em] text-indigo-600">Kiel</p>
                        <p class="text-lg font-black text-slate-950">Dev Portal</p>
                    </div>
                </div>
                <button type="button" class="hidden rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-600 lg:inline-flex" @click="toggleCollapsed">
                    <span x-text="sidebarCollapsed ? '→' : '←'"></span>
                </button>
            </div>

            <nav class="mt-8 space-y-1">
                @foreach ($menuItems as $item)
                    @if ($item['permission'] === null || auth()->user()->can($item['permission']))
                        <a href="{{ route($item['route']) }}" data-app-nav-link @click="handleNavClick($event)" @class(['app-shell-link', 'app-shell-link-active' => $item['active']])>
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-200/80 text-xs font-black text-slate-500">{{ $item['icon'] }}</span>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            <div class="absolute right-0 top-0 hidden h-full w-1 cursor-col-resize bg-transparent hover:bg-indigo-200 lg:block" @mousedown.prevent="startResize"></div>
        </aside>

        <div class="app-shell-content">
            <header class="sticky top-0 z-20 h-20 border-b border-slate-200 bg-slate-50/85 backdrop-blur">
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

            <main class="app-page-content px-4 py-8 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
        function appShell() {
            return {
                sidebarOpen: false,
                sidebarCollapsed: false,
                sidebarWidth: 288,
                sidebarMinWidth: 220,
                sidebarMaxWidth: 420,
                collapsedWidth: 80,
                isResizing: false,
                pageLeaving: false,
                initShell() {
                    this.sidebarCollapsed = localStorage.getItem('kiel.sidebar.collapsed') === '1';
                    const savedWidth = Number.parseInt(localStorage.getItem('kiel.sidebar.width') || '', 10);
                    if (!Number.isNaN(savedWidth)) {
                        this.sidebarWidth = this.clampWidth(savedWidth);
                    }
                    this.applyShellVars();
                },
                clampWidth(width) {
                    return Math.min(this.sidebarMaxWidth, Math.max(this.sidebarMinWidth, width));
                },
                applyShellVars() {
                    const width = this.sidebarCollapsed ? this.collapsedWidth : this.sidebarWidth;
                    this.$el.style.setProperty('--sidebar-width', `${width}px`);
                    this.$el.style.setProperty('--content-left', `${width}px`);
                },
                toggleCollapsed() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    localStorage.setItem('kiel.sidebar.collapsed', this.sidebarCollapsed ? '1' : '0');
                    this.applyShellVars();
                },
                startResize(event) {
                    if (this.sidebarCollapsed) return;
                    this.isResizing = true;
                    document.body.classList.add('select-none', 'is-resizing-sidebar');
                    const onMove = (moveEvent) => {
                        if (!this.isResizing) return;
                        this.sidebarWidth = this.clampWidth(moveEvent.clientX);
                        this.applyShellVars();
                    };
                    const onUp = () => {
                        this.isResizing = false;
                        localStorage.setItem('kiel.sidebar.width', String(this.sidebarWidth));
                        document.body.classList.remove('select-none', 'is-resizing-sidebar');
                        window.removeEventListener('mousemove', onMove);
                        window.removeEventListener('mouseup', onUp);
                    };
                    window.addEventListener('mousemove', onMove);
                    window.addEventListener('mouseup', onUp);
                },
                handleNavClick(event) {
                    const link = event.currentTarget;
                    if (
                        event.defaultPrevented ||
                        event.metaKey ||
                        event.ctrlKey ||
                        event.shiftKey ||
                        event.altKey ||
                        link.target === '_blank'
                    ) {
                        return;
                    }
                    const href = link?.getAttribute('href');
                    if (!href || href === window.location.href) return;
                    this.pageLeaving = true;
                },
            };
        }
    </script>
</body>
</html>
