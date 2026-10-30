{{--
    Memories (App\Support\EventMemories) — the same page for the admin and an
    event's managers; only the routes differ.

      @include('partials.event-memories', ['event' => $event, 'saveUrl' => …, 'notifyUrl' => fn ($kind) => …])
--}}
@php
    $recipients = \App\Support\EventMemories::recipients($event);
    $pagePublic = \App\Support\EventMemories::pageIsPublic($event);
@endphp

<div class="max-w-3xl space-y-6">
    <x-alert type="info">
        After the event, add the official photo album and the talks on WordPress.tv. Attendees see them on the event's Home
        and on its Memories page, which stays open even after the event closes in CampBuddy. Then send each one's
        notification: it goes once, to the {{ number_format($recipients) }} {{ \Illuminate\Support\Str::plural('phone', $recipients) }}
        that turned on reminders for this event, and opens the Memories page (so a link fixed later still reaches them).
    </x-alert>

    <form method="POST" action="{{ $saveUrl }}">
        @csrf
        <x-card title="Links" description="Leave a field empty to remove that link.">
            <div class="grid gap-5">
                @foreach (\App\Support\EventMemories::KINDS as $kind => $meta)
                    <x-form.input :name="$meta['url']" type="url" :label="$meta['label']" :value="$event->{$meta['url']}"
                                  placeholder="https://" :hint="$meta['hint']" />
                @endforeach
            </div>

            <x-slot:footer>
                <div class="flex flex-wrap items-center gap-3">
                    <x-button>Save links</x-button>
                    @if ($pagePublic)
                        <a href="{{ route('event.memories', $event) }}" target="_blank" rel="noopener" class="relative after:absolute after:-inset-y-2.5 after:inset-x-0 after:content-[''] text-sm font-medium text-maroon-text hover:underline">Preview the Memories page</a>
                    @endif
                </div>
            </x-slot:footer>
        </x-card>
    </form>

    <x-card title="Notifications" description="One per link, sent once. Open the link and check it before you send.">
        <x-input-error :messages="$errors->get('notify')" class="mb-3" />
        <ul class="divide-y divide-line">
            @foreach (\App\Support\EventMemories::KINDS as $kind => $meta)
                @php($url = $event->{$meta['url']})
                <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">{{ $meta['label'] }}</p>
                        @if ($url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="block truncate text-xs text-muted hover:text-maroon-text hover:underline">{{ $url }}</a>
                        @else
                            <p class="text-xs text-muted">Add the link first.</p>
                        @endif
                    </div>

                    @if ($event->{$meta['notified']})
                        <x-badge variant="success">Sent {{ $event->{$meta['notified']}->diffForHumans() }}</x-badge>
                    @elseif (! $pagePublic)
                        <span class="text-xs text-muted">The event isn't public, so there's nowhere to send people yet.</span>
                    @elseif ($url && $recipients === 0)
                        <span class="text-xs text-muted">No phone has reminders on for this event yet.</span>
                    @elseif ($url)
                        <x-action-form :action="$notifyUrl($kind)" variant="primary" size="sm" icon="inbox"
                                       :confirm="'Send the '.$meta['label'].' notification to '.$recipients.' phones? It goes once and cannot be undone.'">
                            Send to {{ number_format($recipients) }} {{ \Illuminate\Support\Str::plural('phone', $recipients) }}
                        </x-action-form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
