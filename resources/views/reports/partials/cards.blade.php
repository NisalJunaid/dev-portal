<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $label => $value)
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-soft">
            <p class="text-sm font-black uppercase tracking-wide text-slate-500">{{ str($label)->replace('_', ' ')->headline() }}</p>
            <p class="mt-3 text-4xl font-black text-indigo-700">{{ $value }}</p>
        </div>
    @endforeach
</section>
