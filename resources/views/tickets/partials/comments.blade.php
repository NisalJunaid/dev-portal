<div class="space-y-4">
    @forelse ($comments as $comment)
        <div class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-black text-slate-900">{{ $comment->user->name }}</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $comment->created_at->format('M j, Y g:i A') }}</p>
                </div>
                @if ($comment->is_internal)
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-amber-700">Internal</span>
                @endif
            </div>
            <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $comment->comment }}</p>

            <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mt-4 flex gap-2">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <input name="comment" class="min-w-0 flex-1 rounded-2xl border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reply to this comment">
                @if ($isKielUser)
                    <label class="flex items-center gap-2 rounded-2xl border border-slate-200 px-3 text-xs font-bold text-slate-600">
                        <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        Internal
                    </label>
                @endif
                <button type="submit" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-black text-white">Reply</button>
            </form>

            @if ($comment->replies->count())
                <div class="mt-4 border-l-2 border-slate-200 pl-4">
                    @include('tickets.partials.comments', ['comments' => $comment->replies, 'ticket' => $ticket, 'isKielUser' => $isKielUser])
                </div>
            @endif
        </div>
    @empty
        <div class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No comments yet.</div>
    @endforelse
</div>
