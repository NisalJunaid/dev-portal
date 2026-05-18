<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.css">
<script src="https://cdn.jsdelivr.net/npm/frappe-gantt/dist/frappe-gantt.umd.js" defer></script>

<style>
.gantt .bar.timeline-urgency-critical,.gantt .timeline-urgency-critical .bar,.gantt .bar.timeline-urgency-critical-blocked,.gantt .timeline-urgency-critical-blocked .bar,.gantt .bar.timeline-urgency-critical-overdue,.gantt .timeline-urgency-critical-overdue .bar{fill:#fecdd3}
.gantt .bar.timeline-urgency-high,.gantt .timeline-urgency-high .bar,.gantt .bar.timeline-urgency-high-blocked,.gantt .timeline-urgency-high-blocked .bar,.gantt .bar.timeline-urgency-high-overdue,.gantt .timeline-urgency-high-overdue .bar{fill:#fed7aa}
.gantt .bar.timeline-urgency-medium,.gantt .timeline-urgency-medium .bar,.gantt .bar.timeline-urgency-medium-blocked,.gantt .timeline-urgency-medium-blocked .bar,.gantt .bar.timeline-urgency-medium-overdue,.gantt .timeline-urgency-medium-overdue .bar{fill:#c7d2fe}
.gantt .bar.timeline-urgency-low,.gantt .timeline-urgency-low .bar,.gantt .bar.timeline-urgency-low-blocked,.gantt .timeline-urgency-low-blocked .bar,.gantt .bar.timeline-urgency-low-overdue,.gantt .timeline-urgency-low-overdue .bar{fill:#a7f3d0}
.timeline-previous-sprint .bar,.gantt .bar.timeline-previous-sprint{fill:rgba(148,163,184,.22)!important;stroke:rgba(100,116,139,.35)!important}
.timeline-previous-sprint .bar-label,.gantt .timeline-previous-sprint .bar-label{fill:#475569!important}
.gantt .bar-label{fill:#0f172a !important;font-weight:800}.gantt .bar{stroke:rgba(15,23,42,.12);stroke-width:1}.gantt .bar-wrapper.timeline-urgency-critical-blocked .bar,.gantt .bar-wrapper.timeline-urgency-high-blocked .bar,.gantt .bar-wrapper.timeline-urgency-medium-blocked .bar,.gantt .bar-wrapper.timeline-urgency-low-blocked .bar{stroke-dasharray:5 3}
</style>

<div x-data="timelineView({ dataUrl: @js(route('timeline.data')), dateUrlTemplate: @js(route('timeline.tasks.dates', ['ticket' => '__TICKET__'])), dependencyUrlTemplate: @js(route('timeline.tasks.dependency', ['ticket' => '__TICKET__'])), drawerUrlTemplate: @js(route('tickets.drawer', ['ticket' => '__TICKET__'])), canEdit: @js($canEdit) })" x-init="init()" class="h-full min-h-0" data-timeline-view>
    <section class="asana-panel relative flex h-full min-h-0 flex-col overflow-hidden rounded-none" data-timeline-chart-area>
        <div x-show="loading" x-transition.opacity class="absolute inset-0 z-10 flex items-center justify-center bg-white/75"><p class="text-sm font-black text-slate-700">Loading timeline…</p></div>

        <div class="timeline-shell flex-1 min-h-0 overflow-auto sleek-scrollbar" :class="loading ? 'opacity-50' : 'opacity-100'">
            <div id="timeline-gantt" class="timeline-gantt-inner min-h-full min-w-[960px]" data-timeline-chart></div>
            <div x-show="!loading && tasks.length === 0" class="absolute inset-0 flex items-center justify-center bg-white/60 p-10 text-center text-sm font-bold text-slate-600">No scheduled tasks match these filters.</div>
        </div>
    </section>
</div>

<script>
if (!window.timelineView) {
window.timelineView = function(config) { return {
 dataUrl:config.dataUrl,dateUrlTemplate:config.dateUrlTemplate,dependencyUrlTemplate:config.dependencyUrlTemplate,drawerUrlTemplate:config.drawerUrlTemplate,canEdit:config.canEdit,gantt:null,tasks:[],filters:{ client_id:'',software_id:'',sprint_id:'',assigned_to:'',urgency:'',status:'' },loading:true,selectedTask:null,dependencyDraft:'',savingDependency:false,toast:{ type:'success',message:'' },_resizeHandler:null,
 init(){const params=new URLSearchParams(window.location.search);['client_id','software_id','sprint_id','assigned_to','urgency','status'].forEach(k=>{this.filters[k]=params.get(k)||''});this._resizeHandler=()=>this.expandTimelineHeight();window.addEventListener('resize',this._resizeHandler);this.waitForGantt().then(()=>this.load()).catch(()=>{this.loading=false;this.showToast('Timeline library could not load. Please refresh and try again.','error');});},
 waitForGantt(){return new Promise((resolve,reject)=>{let attempts=0;const timer=setInterval(()=>{attempts++;if(window.Gantt){clearInterval(timer);resolve();}else if(attempts>80){clearInterval(timer);reject();}},100);});},
 async load(){this.loading=true;const params=new URLSearchParams(Object.entries(this.filters).filter(([,v])=>v!==''));try{const payload=await window.Kiel.request(`${this.dataUrl}?${params.toString()}`);this.tasks=payload.tasks||[];this.render();}catch(error){this.showToast(error.message||'Timeline data failed to load.','error');}finally{this.loading=false;this.$nextTick(()=>this.expandTimelineHeight());}},
 render(){const element=document.getElementById('timeline-gantt');if(!element||!window.Gantt)return;element.innerHTML='';if(!this.tasks.length){this.expandTimelineHeight();return;}this.gantt=new window.Gantt(element,this.tasks,{view_mode:'Week',date_format:'YYYY-MM-DD',readonly:!this.canEdit,bar_height:28,padding:24,on_click:task=>this.openDrawer(task),on_date_change:(task,start,end)=>this.saveDates(task,start,end),custom_popup_html:task=>this.popupHtml(task)});this.$nextTick(()=>this.expandTimelineHeight());setTimeout(()=>this.expandTimelineHeight(),50);},
 expandTimelineHeight(){const element=document.getElementById('timeline-gantt');const shell=element?.closest('.timeline-shell');if(!element||!shell)return;const minHeight=shell.clientHeight;element.style.minHeight=`${minHeight}px`;const container=element.querySelector('.gantt-container');const svg=element.querySelector('svg');if(container)container.style.minHeight=`${minHeight}px`;if(svg){const h=Number(svg.getAttribute('height')||0);if(h<minHeight){svg.setAttribute('height',String(minHeight));svg.style.minHeight=`${minHeight}px`;}}},
 popupHtml(task){const ticket=task.ticket||{};return `<div class="rounded-2xl bg-white p-4 text-sm shadow-2xl"><p class="font-black text-indigo-600">${ticket.ticket_no||''}</p><p class="mt-1 font-black text-slate-950">${ticket.title||task.name}</p><p class="mt-2 text-slate-600">${ticket.assignee||'Unassigned'} · ${ticket.status_label||''} · ${ticket.urgency_label||''}</p></div>`;},
 async saveDates(task,start,end){if(!this.canEdit){this.showToast('This timeline is read only.','error');this.render();return;}try{const payload=await window.Kiel.request(this.dateUrlTemplate.replace('__TICKET__',task.id),{method:'PATCH',body:JSON.stringify({ start_date:this.formatDate(start),due_date:this.formatDate(end) })});this.replaceTask(payload.task);this.showToast('Timeline dates saved.','success');}catch(error){this.showToast(error.message||'Timeline date save failed.','error');await this.load();}},
 replaceTask(task){this.tasks=this.tasks.map(existing=>existing.id===task.id?task:existing);this.selectedTask=this.tasks.find(existing=>existing.id===task.id)||task;},
 openDrawer(task){this.selectedTask=task;window.ticketDrawer?.open(this.drawerUrlTemplate.replace('__TICKET__',task.id));},
 formatDate(value){const date=new Date(value);return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;},
 showToast(message,type='success'){this.toast={message,type};window.Kiel?.toast(message,type);}
};};
}
</script>
