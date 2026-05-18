<x-app-layout>
    <x-slot name="header">Tasks</x-slot>

    <div x-data="taskWorkspace({ initialView: @js($activeView), initialWorkType: @js($workType), initialFiltersOpen: false, boardColumns: @js(collect($kanbanColumns)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()) })" x-init="init()" class="tasks-shell" @keydown.escape.window="closeFieldMenu(); closeDropdowns(); closeCreateTaskDrawer();">
        <div x-cloak x-show="openMenu" class="fixed inset-0 z-40" @click="closeDropdowns()"></div>

        
        <div x-cloak x-show="fieldMenu.open" class="fixed inset-0 z-[120]" @click="closeFieldMenu()">
            <div x-show="fieldMenu.open" x-transition.opacity.scale.origin.top.left class="field-floating-menu fixed z-[130] max-h-[320px] min-w-48 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-2xl sleek-scrollbar" :style="`top:${fieldMenu.y}px; left:${fieldMenu.x}px; width:${fieldMenu.width}px;`" @click.stop>
                <template x-for="option in fieldMenu.options" :key="option.value">
                    <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="selectFieldMenuOption(option.value)">
                        <span x-text="option.label"></span><span x-show="option.value === fieldMenu.currentValue" class="text-indigo-600">✓</span>
                    </button>
                </template>
            </div>
        </div>

        <div class="tasks-workspace">
            <section class="tasks-toolbar">
                <div class="flex items-center gap-2">
                    <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1">
                        <template x-for="view in views" :key="view.key">
                            <button type="button" class="rounded-lg p-2 transition" :class="activeView === view.key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" :title="view.label" @click="setView(view.key)">
                                <span class="sr-only" x-text="view.label"></span><span x-html="view.icon"></span>
                            </button>
                        </template>
                    </div>
                </div>


                <div class="justify-self-center inline-flex items-center rounded-xl border border-slate-200 bg-white p-1">
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="workType==='tasks' ? 'bg-slate-900 text-white' : 'text-slate-600'" @click="setWorkType('tasks')">Tasks</button>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="workType==='bugs' ? 'bg-slate-900 text-white' : 'text-slate-600'" @click="setWorkType('bugs')">Bugs</button>
                </div>
                <div class="flex items-center gap-2 justify-self-end">
                    <button type="button" class="inline-flex items-center rounded-xl border border-slate-200 p-2 text-slate-700 transition hover:border-slate-300" title="Filters" @click="toggleFilters" :aria-expanded="showFilters.toString()">
                        <span class="sr-only">Filters</span><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14v2H3V5zm3 4h8v2H6V9zm3 4h2v2H9v-2z"/></svg>
                    </button>

                    <div class="relative" x-cloak x-show="activeView === 'list'" @click.stop>
                        <button type="button" class="relative inline-flex items-center rounded-xl border border-slate-200 p-2 text-slate-700 transition hover:border-slate-300" title="Columns" @click.stop="openDropdown('listColumns')" :aria-expanded="(openMenu === 'listColumns').toString()">
                            <span class="sr-only">Columns</span><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4h14v12H3V4Zm4 1H4v10h3V5Zm1 0v10h4V5H8Zm5 0v10h3V5h-3Z"/></svg>
                            <span x-show="$store.taskColumns.hiddenCount() > 0" class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                        </button>

                        <div x-cloak x-show="openMenu === 'listColumns' && activeView === 'list'" @click.stop x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="absolute right-0 top-12 z-50 w-64 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                            <div class="mb-1 flex items-center justify-between px-1">
                                <p class="text-xs font-bold text-slate-600">Columns</p>
                                <button type="button" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700" @click.stop="$store.taskColumns.reset()">Reset</button>
                            </div>
                            <div class="max-h-72 space-y-1 overflow-y-auto sleek-scrollbar">
                                <template x-for="column in $store.taskColumns.columns" :key="column.key">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" class="rounded border-slate-300 text-indigo-600" :checked="$store.taskColumns.isVisible(column.key)" @change.stop="$store.taskColumns.toggle(column.key)">
                                        <span x-text="column.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="relative" x-cloak x-show="activeView === 'board'" @click.stop>
                        <button type="button" class="relative inline-flex items-center rounded-xl border border-slate-200 p-2 text-slate-700 transition hover:border-slate-300" title="Boards" @click.stop="openDropdown('boardColumns')" :aria-expanded="(openMenu === 'boardColumns').toString()">
                            <span class="sr-only">Boards</span><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4h14v12H3V4Zm1 1v10h3V5H4Zm4 0v10h4V5H8Zm5 0v10h3V5h-3Z"/></svg>
                            <span x-show="$store.kanbanColumns.hiddenCount() > 0" class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                        </button>
                        <div x-cloak x-show="openMenu === 'boardColumns' && activeView === 'board'" @click.stop x-transition.opacity.duration.150ms class="absolute right-0 top-12 z-50 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="mb-2 text-xs font-black uppercase tracking-widest text-slate-500">Visible boards</p>
                            <div class="max-h-72 space-y-1 overflow-y-auto sleek-scrollbar">
                                <template x-for="column in $store.kanbanColumns.columns" :key="column.key">
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" class="rounded border-slate-300 text-indigo-600" :checked="$store.kanbanColumns.isVisible(column.key)" @change.stop="toggleBoardColumn(column.key)">
                                        <span x-text="column.label"></span>
                                    </label>
                                </template>
                            </div>
                            <button type="button" class="mt-3 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50" @click.stop="resetBoardColumns()">Reset boards</button>
                        </div>
                    </div>

                    @if ($isKielUser)
                        <button type="button" data-create-task-trigger class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white" @click.stop="openCreateTaskDrawer()">Add new</button>
                    @endif
                </div>
            </section>

            <section x-cloak x-show="showFilters" x-collapse x-transition.opacity.duration.150ms class="tasks-filter-panel">
                <form method="GET" action="{{ route('tasks.index') }}" class="grid gap-3 xl:grid-cols-[minmax(14rem,1fr)_repeat(8,minmax(0,10rem))_auto]">
                    <input type="hidden" name="view" :value="activeView">
                    <input type="hidden" name="work_type" :value="workType">
                    <label class="xl:col-span-2"><span class="sr-only">Search tickets</span><input name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Search..." class="w-full rounded-xl border-slate-200 text-sm"></label>
                    <select name="scope" class="rounded-xl border-slate-200 text-sm"><option value="current_sprint" @selected(($filters['scope'] ?? 'current_sprint')==='current_sprint')>Current sprint</option><option value="all" @selected(($filters['scope'] ?? '')==='all')>All items</option><option value="unsprinted" @selected(($filters['scope'] ?? '')==='unsprinted')>Unsprinted</option><option value="completed_sprints" @selected(($filters['scope'] ?? '')==='completed_sprints')>Completed sprints</option><option value="sprint" @selected(($filters['scope'] ?? '')==='sprint')>Specific sprint</option></select>
                    <select name="sprint_id" class="rounded-xl border-slate-200 text-sm"><option value="">Any sprint</option>@foreach ($sprints as $sprint)<option value="{{ $sprint->id }}" @selected((string)($filters['sprint_id'] ?? '')===(string)$sprint->id)>{{ $sprint->name }}</option>@endforeach</select>
                    <select name="urgency" class="rounded-xl border-slate-200 text-sm"><option value="">All urgency</option>@foreach (\App\Models\Ticket::URGENCIES as $urgency)<option value="{{ $urgency }}" @selected(($filters['urgency'] ?? '') === $urgency)>{{ str($urgency)->headline() }}</option>@endforeach</select>
                    <select name="status" class="rounded-xl border-slate-200 text-sm"><option value="">All statuses</option>@foreach (\App\Models\Ticket::STATUSES as $statusOption)<option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ str($statusOption)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                    <select name="assigned_to" class="rounded-xl border-slate-200 text-sm"><option value="">Any assignee</option><option value="unassigned" @selected(($filters['assigned_to'] ?? '') === 'unassigned')>Unassigned</option>@foreach ($teamMembers as $member)<option value="{{ $member->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $member->id)>{{ $member->name }}</option>@endforeach</select>
                    @if ($isKielUser)<select name="client_id" class="rounded-xl border-slate-200 text-sm"><option value="">All clients</option>@foreach ($clients as $client)<option value="{{ $client->id }}" @selected((string) ($filters['client_id'] ?? '') === (string) $client->id)>{{ $client->name }}</option>@endforeach</select>@endif
                    <select name="software_id" class="rounded-xl border-slate-200 text-sm"><option value="">All software</option>@foreach ($softwares as $software)<option value="{{ $software->id }}" @selected((string) ($filters['software_id'] ?? '') === (string) $software->id)>{{ $software->name }}</option>@endforeach</select>
                    <select name="per_page" class="rounded-xl border-slate-200 text-sm">@foreach ([10, 25, 50, 100] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}/page</option>@endforeach</select>
                    <div class="flex gap-2"><button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-black text-white">Apply</button><a href="{{ route('tasks.index', ['view' => $activeView, 'scope' => 'current_sprint']) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-600">Reset</a></div>
                </form>
            </section>

            @if ($currentSprint)
                <section class="mb-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <div class="flex items-center gap-3"><span class="font-bold">{{ $currentSprint->name }}</span><span class="text-slate-500">{{ $currentSprint->client->name }}</span><span id="tasks-sprint-timer" data-elapsed="{{ $currentSprintStats['elapsed_seconds'] ?? 0 }}">00:00:00</span></div>
                    @if($isKielUser)
                    <div class="flex items-center gap-2">
                        <button type="button" class="sprint-icon-button" title="Pause sprint" @click="pauseSprint({{ $currentSprint->id }})" x-show="('{{ $currentSprint->timer_status }}'==='running')"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="3" width="4" height="14"/><rect x="12" y="3" width="4" height="14"/></svg><span class="sr-only">Pause sprint</span></button>
                        <button type="button" class="sprint-icon-button" title="Resume sprint" @click="resumeSprint({{ $currentSprint->id }})" x-show="('{{ $currentSprint->timer_status }}'==='paused')"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><polygon points="5,3 17,10 5,17"/></svg><span class="sr-only">Resume sprint</span></button>
                        <button type="button" class="sprint-icon-button" title="End sprint" @click="endSprint({{ $currentSprint->id }})" @disabled(!($currentSprintStats['can_end'] ?? false))><svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="4" width="12" height="12"/></svg><span class="sr-only">End sprint</span></button>
                    </div>
                    @endif
                </section>
            @endif

            <section class="tasks-content-panel">
                <div x-cloak x-show="activeView === 'list'" x-ref="listView" class="h-full" x-transition.opacity.duration.150ms>@include('tasks.partials.list-view')</div>
                <div x-cloak x-show="activeView === 'board'" x-ref="boardView" class="h-full" x-transition.opacity.duration.150ms>@include('tasks.partials.kanban-view', ['activeView' => 'all', 'columns' => $kanbanColumns, 'ticketsByColumn' => $kanbanTicketsByColumn, 'canMove' => $canMove])</div>
                <div x-cloak x-show="activeView === 'timeline'" class="h-full" x-transition.opacity.duration.150ms>@include('tasks.partials.timeline-view', ['canEdit' => $canEditTimeline])</div>
            </section>
        </div>

        @include('tickets.partials.drawer')
        @include('tasks.partials.create-task-drawer')
    </div>
<script>
function taskWorkspace(config){return{activeView:config.initialView||'list',workType:config.initialWorkType||'tasks',showFilters:config.initialFiltersOpen??false,openMenu:null,createTaskDrawerOpen:false,createTaskSubmitting:false,createTaskForm:{title:'',description:'',software_id:'',urgency:'',type:'task',is_bug:false,parent_ticket_id:''},createTaskErrors:{},createFeatureDrawerOpen:false,createFeatureForm:{title:'',description:'',software_id:'',urgency:'',parent_ticket_id:''},fieldMenu:{open:false,type:null,ticketId:null,endpoint:null,currentValue:null,options:[],x:0,y:0,width:220,trigger:null},views:[{ key:'list',label:'List',icon:'<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 5h14v2H3V5zm0 4h14v2H3V9zm0 4h14v2H3v-2z"/></svg>'},{ key:'board',label:'Board',icon:'<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4h4v12H3V4zm5 0h4v12H8V4zm5 0h4v12h-4V4z"/></svg>'},{ key:'timeline',label:'Timeline',icon:'<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4h12v2H4V4zm0 4h6v2H4V8zm8 0h4v2h-4V8zM4 12h4v2H4v-2zm6 0h6v2h-6v-2z"/></svg>'}],init(){window.addEventListener('kiel:add-subfeature',e=>{this.createFeatureForm.parent_ticket_id=e.detail.parent_ticket_id;this.openCreateFeatureDrawer();});window.addEventListener('kiel:add-subtask',e=>{this.createTaskForm.parent_ticket_id=e.detail.parent_ticket_id;this.openCreateTaskDrawer();});const saved=localStorage.getItem('kiel.tasks.filters.open');this.showFilters=saved==='1';this.$store.taskColumns.init();this.$store.kanbanColumns.init(config.boardColumns||[]);window.KielTasks.refreshView=(v)=>this.refreshView(v||this.activeView);this.$nextTick(()=>this.initView());},initView(){if(this.activeView==='board')window.KielKanban?.initAll?.();if(this.activeView==='timeline')window.KielTimeline?.reloadAll?.();},openFieldMenu(event,config){event.preventDefault();event.stopPropagation();const rect=event.currentTarget.getBoundingClientRect();const menuWidth=Math.max(rect.width,config.width||220);let x=rect.left;let y=rect.bottom+6;const p=12;if(x+menuWidth>window.innerWidth-p){x=window.innerWidth-menuWidth-p;}const h=Math.min(((config.options?.length)||5)*38+12,320);if(y+h>window.innerHeight-p){y=Math.max(p,rect.top-h-6);}this.fieldMenu={open:true,type:config.type,ticketId:config.ticketId,endpoint:config.endpoint,currentValue:config.currentValue??'',options:config.options||[],x,y,width:menuWidth,trigger:event.currentTarget};},closeFieldMenu(){this.fieldMenu.open=false;},async selectFieldMenuOption(value){const menu={...this.fieldMenu};this.closeFieldMenu();if(!menu.endpoint||value===menu.currentValue)return;const field=menu.type==='assignee'?'assigned_to':'status';try{const payload=await window.Kiel.request(menu.endpoint,{method:'PATCH',body:JSON.stringify({field,value})});if(payload.removed_from_tasks){window.KielTasks?.emitRemoved?.(payload.ticket?.id||menu.ticketId,'removed_from_tasks');}else if(payload.ticket){window.KielTasks?.emitTaskUpdated?.(payload.ticket);}window.Kiel.toast(payload.message||'Updated.');}catch(error){window.Kiel.toast(error.message||'Unable to update.','error');}},toggleFilters(){this.showFilters=!this.showFilters;localStorage.setItem('kiel.tasks.filters.open',this.showFilters?'1':'0');},openDropdown(name){this.closeFieldMenu();this.openMenu=this.openMenu===name?null:name;},closeDropdowns(){this.openMenu=null;},openCreateTaskDrawer(){this.closeDropdowns();this.createTaskDrawerOpen=true;this.createTaskErrors={};this.$nextTick(()=>{document.querySelector('[data-create-task-title]')?.focus();});},closeCreateTaskDrawer(){this.createTaskDrawerOpen=false;this.createTaskSubmitting=false;this.createTaskErrors={};},openCreateFeatureDrawer(){this.createFeatureDrawerOpen=true;},closeCreateFeatureDrawer(){this.createFeatureDrawerOpen=false;},async submitCreateTask(){this.createTaskSubmitting=true;this.createTaskErrors={};try{const payload=await window.Kiel.request("{{ route('tickets.store') }}",{method:'POST',body:JSON.stringify({...this.createTaskForm,type:this.createTaskForm.is_bug?'bug':'task'})});window.Kiel.toast(payload.message||'Task created successfully.');this.closeCreateTaskDrawer();this.createTaskForm={title:'',description:'',software_id:'',urgency:'',type:'task',is_bug:false,parent_ticket_id:''};if(payload.work_type&&payload.work_type!==this.workType){this.setWorkType(payload.work_type);}await this.refreshView(this.activeView);}catch(error){if(error.payload?.errors){this.createTaskErrors=Object.fromEntries(Object.entries(error.payload.errors).map(([k,v])=>[k,v?.[0]||'']));}else{this.createTaskErrors={global:error.message||'Unable to create task.'};window.Kiel.toast(this.createTaskErrors.global,'error');}}finally{this.createTaskSubmitting=false;}},async submitCreateFeature(){try{const payload=await window.Kiel.request("{{ route('features.request.store') }}",{method:'POST',body:JSON.stringify(this.createFeatureForm)});window.Kiel.toast(payload.message||'Feature request created.');this.closeCreateFeatureDrawer();this.createFeatureForm={title:'',description:'',software_id:'',urgency:'',parent_ticket_id:''};if(payload.work_type&&payload.work_type!==this.workType){this.setWorkType(payload.work_type);}await this.refreshView(this.activeView);}catch(error){window.Kiel.toast(error.message||'Unable to create feature request.','error');}},async refreshView(view){if(view==='timeline'){window.KielTimeline?.reloadAll?.();return;}const target=view==='list'?this.$refs.listView:this.$refs.boardView;const url=new URL("{{ route('tasks.partial') }}",window.location.origin);const params=new URLSearchParams(window.location.search);params.set('view',view);url.search=params.toString();const payload=await window.Kiel.request(url.toString());if(target&&payload.html){target.innerHTML=payload.html;window.Alpine?.initTree?.(target);if(view==='list')this.$nextTick(()=>window.KielTaskList?.initAll?.(true));if(view==='board'){this.$store.kanbanColumns.init(payload.board_columns||[]);this.$nextTick(()=>window.KielKanban?.initAll?.(true));}}},toggleBoardColumn(key){this.$store.kanbanColumns.toggle(key);this.$nextTick(()=>window.KielKanban?.initAll?.(true));},resetBoardColumns(){this.$store.kanbanColumns.reset();this.$nextTick(()=>window.KielKanban?.initAll?.(true));},setWorkType(type){if(this.workType===type)return;this.workType=type;this.closeFieldMenu();this.closeDropdowns();const url=new URL(window.location);url.searchParams.set('work_type',type);url.searchParams.delete('page');window.history.pushState({},'',url);this.refreshView(this.activeView);},pauseSprint(id){window.Kiel.request(`/sprints/${id}/pause`,{method:'PATCH'}).then(()=>window.location.reload());},resumeSprint(id){window.Kiel.request(`/sprints/${id}/resume`,{method:'PATCH'}).then(()=>window.location.reload());},endSprint(id){window.Kiel.request(`/sprints/${id}/end`,{method:'PATCH'}).then(()=>window.location.reload());},setView(view){this.activeView=view;this.closeDropdowns();const url=new URL(window.location);url.searchParams.set('view',view);window.history.pushState({},'',url);if(window.KielTasks?.isDirty?.(view)){this.refreshView(view).then(()=>window.KielTasks?.clearDirty?.(view));}this.$nextTick(()=>this.initView());}}}
</script>
</x-app-layout>
