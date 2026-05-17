<x-app-layout>
    <x-slot name="header">Sprints</x-slot>
    <div x-data="sprintDashboard()" class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-black">Sprint Planning</h2>
            <button type="button" class="rounded-xl border px-3 py-2" @click="openCreateFeatureDrawer()">New Feature Request</button>
        </div>

        <section class="card">
            <h3 class="font-black mb-2">Current Sprint</h3>
            @if($currentSprint)
                <div class="text-sm">{{ $currentSprint->name }} · {{ $currentSprint->client->name }} · <span id="sprint-timer" data-elapsed="{{ $currentSprintStats['elapsed_seconds'] ?? 0 }}"></span></div>
            @else
                <p class="text-sm text-slate-500">No active sprint.</p>
            @endif
        </section>

        <section class="card">
            <h3 class="font-black mb-2">Approved for Current Sprint</h3>
            @foreach($approvedFeatures as $feature)
                <div class="flex justify-between text-sm py-1"><span>{{ $feature->title }}</span>@if($isKielUser)<button @click="removeFromSprint({{ $feature->id }})">Remove</button>@endif</div>
            @endforeach
        </section>

        <section class="card">
            <h3 class="font-black mb-2">Future Feature Request Backlog</h3>
            @foreach($futureFeatures as $feature)
                <div class="flex justify-between text-sm py-1"><span>{{ $feature->title }}</span>@if($isKielUser)<button @click="approveForSprint({{ $feature->id }})">Approve</button>@endif</div>
            @endforeach
        </section>

        <section class="card">
            <h3 class="font-black mb-2">Completed / Historical Sprints</h3>
            @foreach($sprints as $sprint)
                <a class="block text-sm py-1" href="{{ route('sprints.show', $sprint) }}">{{ $sprint->name }} ({{ $sprint->formattedStatus() }})</a>
            @endforeach
        </section>

        @include('tasks.partials.create-feature-drawer')
    </div>
<script>
function sprintDashboard(){return{openCreateFeatureDrawer(){this.createFeatureDrawerOpen=true;},createFeatureDrawerOpen:false,createFeatureForm:{title:'',description:'',software_id:'',urgency:'',parent_ticket_id:''},closeCreateFeatureDrawer(){this.createFeatureDrawerOpen=false;},async submitCreateFeature(){try{await window.Kiel.request("{{ route('features.request.store') }}",{method:'POST',body:JSON.stringify(this.createFeatureForm)});window.location.reload();}catch(e){window.Kiel.toast(e.message,'error')}},async approveForSprint(id){await window.Kiel.request(`/features/${id}/approve-next-sprint`,{method:'POST'});window.location.reload();},async removeFromSprint(id){await window.Kiel.request(`/features/${id}/remove-from-sprint`,{method:'PATCH'});window.location.reload();}}}
</script>
</x-app-layout>
