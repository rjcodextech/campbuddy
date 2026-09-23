<x-app-layout :title="'Deal Leads — '.$event->display_name">
    <div class="max-w-4xl space-y-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-line p-6">
            <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-muted uppercase mb-1">Deal</label>
                    <select name="offer_id" class="border-line rounded-md text-sm">
                        <option value="">All deals</option>
                        @foreach ($offers as $offer)
                            <option value="{{ $offer->id }}" @selected(($filters['offer_id'] ?? '') == $offer->id)>{{ $offer->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-muted uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="border-line rounded-md text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-muted uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="border-line rounded-md text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-paper-soft text-ink text-sm font-medium rounded-md hover:bg-line">Filter</button>
                <a href="{{ route('admin.events.deal-leads.export', array_merge(['event' => $event], $filters)) }}"
                   class="px-4 py-2 bg-maroon text-white text-sm font-medium rounded-md hover:bg-maroon-dark">
                    Export CSV
                </a>
            </form>

            <table class="min-w-full divide-y divide-line">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Deal</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Name</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Email</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Mobile</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-muted uppercase">Submitted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($leads as $lead)
                        <tr>
                            <td class="px-3 py-2 text-sm text-ink">{{ $lead->offer?->title ?? '—' }}</td>
                            <td class="px-3 py-2 text-sm text-ink">{{ $lead->name }}</td>
                            <td class="px-3 py-2 text-sm text-ink">{{ $lead->email }}</td>
                            <td class="px-3 py-2 text-sm text-ink">{{ $lead->mobile ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-muted">{{ $lead->created_at->format('d M Y, g:ia') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-muted">No leads captured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $leads->links() }}</div>
        </div>
    </div>
</x-app-layout>
