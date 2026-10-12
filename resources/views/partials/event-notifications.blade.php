{{--
    Every push CampBuddy sends about the event (App\Support\EventNotifications)
    — the same page for the admin and the event's managers.

      @include('partials.event-notifications', ['event' => $event, 'memoriesUrl' => …])
--}}
@php
    $rows = \App\Support\EventNotifications::rows($event);
    $phones = \App\Support\EventNotifications::phones($event);
    $zoneName = \App\Support\EventTime::zone($event)->getName();
    $badge = ['sent' => 'success', 'scheduled' => 'info', 'auto' => 'info', 'ready' => 'warning', 'waiting' => 'neutral', 'missed' => 'neutral'];
    $label = ['sent' => 'Sent', 'scheduled' => 'Scheduled', 'auto' => 'Automatic', 'ready' => 'Ready to send', 'waiting' => 'Waiting', 'missed' => 'Not sent'];
@endphp

<div class="max-w-4xl space-y-6">
    <x-alert type="info">
        Every push goes to the {{ $phones }} that turned on reminders for this event, once, and opens the right page in CampBuddy.
        The ones after the event go by themselves in the venue's daytime; the photo album and WordPress.tv ones go when you send them from
        <a href="{{ $memoriesUrl }}" class="font-medium underline">Memories</a>.
    </x-alert>

    <x-card title="Notifications" :description="'Times are the venue’s ('.$zoneName.').'" flush>
        <ul class="divide-y divide-line">
            @foreach ($rows as $row)
                <li class="flex flex-wrap items-start justify-between gap-3 px-5 py-4" data-notification="{{ $row['key'] }}">
                    <div class="min-w-0 flex-1 basis-72">
                        <p class="text-sm font-semibold">{{ $row['title'] }} <span class="font-normal text-muted">· {{ $row['when'] }}</span></p>
                        @if ($row['text'] !== '')
                            <p class="mt-1 text-sm text-muted">“{{ $row['text'] }}”</p>
                        @endif
                        <p class="mt-1 text-xs text-muted">{{ $row['status'] }}</p>
                    </div>
                    <x-badge :variant="$badge[$row['state']] ?? 'neutral'">{{ $label[$row['state']] ?? $row['state'] }}</x-badge>
                </li>
            @endforeach
        </ul>
    </x-card>
</div>
