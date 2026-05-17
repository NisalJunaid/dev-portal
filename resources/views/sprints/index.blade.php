<x-app-layout>
    <x-slot name="header">Sprints</x-slot>
    <div x-data="sprintDashboard(@js([
        'currentSprintId' => $currentSprint?->id,
        'elapsed' => $currentSprintStats['elapsed_seconds'] ?? 0,
        'running' => $currentSprint?->timer_status === \App\Models\Sprint::TIMER_RUNNING,
    ]))" class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-black">Sprints</h2>
            <div class="flex items-center gap-2">
                <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="openCreateFeatureDrawer()">New Feature Request</button>
                @if(!$currentSprint && $approvedFeatures->isNotEmpty() && $isKielUser)
                    <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-bold text-white" @click="startSprint">Start Sprint</button>
                @endif
            </div>
        </div>

        <section class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-black">Current Sprint</h3>
                    @if($currentSprint)
                        <p class="text-sm">{{ $currentSprint->name }} · {{ $currentSprint->client->name }} · <span x-text="formattedTimer"></span></p>
                    @else
                        <p class="text-sm text-slate-500">No active sprint.</p>
                    @endif
                </div>
                @if($currentSprint && $isKielUser)
                    <div class="flex items-center gap-2">
                        <button x-show="running" @click="pauseSprint({{ $currentSprint->id }})" title="Pause sprint" class="rounded-full border px-3 py-1">⏸</button>
                        <button x-show="!running" @click="resumeSprint({{ $currentSprint->id }})" title="Resume sprint" class="rounded-full border px-3 py-1">▶</button>
                        <button @click="endSprint({{ $currentSprint->id }})" title="End sprint" class="rounded-full border px-3 py-1">⏹</button>
                    </div>
                @endif
            </div>
        </section>

        <section class="card p-4">
            <h3 class="font-black mb-3">Approved for Current Sprint</h3>
            <table class="min-w-full text-sm"><thead><tr class="text-left"><th>Feature</th><th>Software</th><th>Status</th><th>Approved</th></tr></thead><tbody>
                @forelse($approvedFeatures as $feature)
                    <tr><td>{{ $feature->ticket_no }} · {{ $feature->title }}</td><td>{{ $feature->software?->name }}</td><td>{{ $feature->formattedStatus() }}</td><td>
                        @if($isKielUser)
                        <button type="button" @click="removeFromSprint({{ $feature->id }})" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-black text-white">On</button>
                        @else <span class="text-xs rounded-full bg-emerald-100 px-2 py-1">Approved for sprint</span> @endif
                    </td></tr>
                @empty <tr><td colspan="4" class="text-slate-500">No approved features.</td></tr>
                @endforelse
            </tbody></table>
        </section>

        <section class="card p-4">
            <h3 class="font-black mb-3">Future Feature Request Backlog</h3>
            <table class="min-w-full text-sm"><thead><tr class="text-left"><th>Feature</th><th>Software</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @forelse($futureFeatures as $feature)
                <tr><td>{{ $feature->ticket_no }} · {{ $feature->title }}</td><td>{{ $feature->software?->name }}</td><td>{{ $feature->formattedStatus() }}</td><td>
                    @if($isKielUser)
                    <button type="button" @click="approveForSprint({{ $feature->id }})" class="inline-flex items-center gap-2 rounded-full bg-slate-700 px-3 py-1.5 text-xs font-black text-white">Off</button>
                    @elseif($feature->status===\App\Models\Ticket::STATUS_FEATURE_APPROVED)
                    <form method="POST" action="{{ route('features.recommend',$feature) }}">@csrf<button class="text-xs text-indigo-700">Recommend</button></form>
                    @else <span class="text-xs">Recommended</span> @endif
                </td></tr>
                @empty <tr><td colspan="4" class="text-slate-500">No backlog features.</td></tr>
                @endforelse
            </tbody></table>
        </section>
        @include('tasks.partials.create-feature-drawer')
    </div>
<script>
function sprintDashboard(config){return{elapsed:config.elapsed||0,running:!!config.running,tick:null,get formattedTimer(){const s=this.elapsed;const h=String(Math.floor(s/3600)).padStart(2,'0');const m=String(Math.floor((s%3600)/60)).padStart(2,'0');const sec=String(s%60).padStart(2,'0');return `${h}:${m}:${sec}`;},init(){if(this.running){this.startTick();}},startTick(){clearInterval(this.tick);this.tick=setInterval(()=>{if(this.running)this.elapsed++;},1000);},openCreateFeatureDrawer(){this.createFeatureDrawerOpen=true;},createFeatureDrawerOpen:false,createFeatureForm:{title:'',description:'',software_id:'',urgency:'',parent_ticket_id:''},closeCreateFeatureDrawer(){this.createFeatureDrawerOpen=false;},async submitCreateFeature(){try{await window.Kiel.request("{{ route('features.request.store') }}",{method:'POST',body:JSON.stringify(this.createFeatureForm)});window.Kiel.toast('Feature request created.');location.reload();}catch(e){window.Kiel.toast(e.message,'error')}},async approveForSprint(id){await window.Kiel.request(`/features/${id}/approve-next-sprint`,{method:'POST'});window.Kiel.toast('Approved.');location.reload();},async removeFromSprint(id){await window.Kiel.request(`/features/${id}/remove-from-sprint`,{method:'PATCH'});window.Kiel.toast('Removed from sprint queue.');location.reload();},async startSprint(){await window.Kiel.request("{{ route('sprints.start.store') }}",{method:'POST',body:JSON.stringify({client_id:"{{ ($filters['client_id'] ?? auth()->user()->client_id) }}"})});window.Kiel.toast('Sprint started.');location.reload();},pauseSprint(id){window.Kiel.request(`/sprints/${id}/pause`,{method:'PATCH'}).then(()=>{this.running=false;window.Kiel.toast('Sprint paused.');});},resumeSprint(id){window.Kiel.request(`/sprints/${id}/resume`,{method:'PATCH'}).then(()=>{this.running=true;window.Kiel.toast('Sprint resumed.');});},endSprint(id){window.Kiel.request(`/sprints/${id}/end`,{method:'PATCH'}).then(()=>{this.running=false;window.Kiel.toast('Sprint ended.');location.reload();});}}}
</script>
</x-app-layout>
