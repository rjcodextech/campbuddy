<x-app-layout title="Feedback" subtitle="What attendees said on the thank-you card after each WordCamp. No names: every answer is anonymous."
              :breadcrumbs="[['Feedback']]">
    <x-card title="By WordCamp" description="One answer per phone; someone who answers again changes their earlier answer." flush>
        <x-table>
            <x-slot:head>
                <th>WordCamp</th>
                <th class="text-right">Answers</th>
                <th class="text-right">Average</th>
                <th class="hidden md:table-cell">Stars</th>
                <th class="hidden text-right sm:table-cell">Comments</th>
            </x-slot:head>
            @forelse ($summary as $row)
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('admin.feedback.index', ['event' => $row->event_id]) }}" class="hover:text-maroon hover:underline">{{ $row->event?->display_name ?? 'Deleted event' }}</a>
                    </td>
                    <td class="text-right tabular-nums">{{ number_format($row->answers) }}</td>
                    <td class="whitespace-nowrap text-right tabular-nums"><span class="text-gold" aria-hidden="true">★</span> {{ number_format($row->average, 1) }}</td>
                    <td class="hidden md:table-cell">
                        <div class="flex h-2.5 w-48 overflow-hidden rounded bg-paper-soft" title="5★ {{ $row->r5 }} · 4★ {{ $row->r4 }} · 3★ {{ $row->r3 }} · 2★ {{ $row->r2 }} · 1★ {{ $row->r1 }}">
                            @foreach ([5 => 'bg-teal', 4 => 'bg-teal/60', 3 => 'bg-gold', 2 => 'bg-maroon/60', 1 => 'bg-maroon'] as $stars => $color)
                                <span class="{{ $color }}" style="width: {{ $row->answers ? round($row->{'r'.$stars} / $row->answers * 100, 1) : 0 }}%"></span>
                            @endforeach
                        </div>
                    </td>
                    <td class="hidden text-right tabular-nums sm:table-cell">{{ number_format($row->comments) }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5" icon="inbox" title="No feedback yet">
                    The thank-you card opens three days after a WordCamp ends.
                </x-table.empty>
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="$eventId ? 'Answers for '.($events[$eventId] ?? 'this WordCamp') : 'All answers'" class="mt-6" flush>
        <x-slot:actions>
            @if ($eventId)
                <x-button :href="route('admin.feedback.index')" variant="secondary" size="sm">Show all</x-button>
            @endif
        </x-slot:actions>
        <x-table>
            <x-slot:head>
                <th>Rating</th>
                <th>Comment</th>
                <th class="hidden sm:table-cell">WordCamp</th>
                <th class="hidden md:table-cell">When</th>
            </x-slot:head>
            @forelse ($feedback as $answer)
                <tr class="align-top">
                    <td class="whitespace-nowrap text-gold" aria-label="{{ $answer->rating }} out of 5">{{ str_repeat('★', $answer->rating) }}<span class="text-line">{{ str_repeat('★', 5 - $answer->rating) }}</span></td>
                    <td class="min-w-[14rem] break-words">{{ $answer->comment ?? '—' }}</td>
                    <td class="hidden sm:table-cell">{{ $answer->event?->display_name }}</td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell" title="{{ $answer->updated_at }}">{{ $answer->updated_at->diffForHumans() }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="inbox" title="No answers here yet" />
            @endforelse
        </x-table>

        @if ($feedback->hasPages())
            <x-slot:footer>{{ $feedback->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-app-layout>
