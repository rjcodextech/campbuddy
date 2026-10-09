{{--
    Contributor Day tables (App\Support\ContributorTables) — the same page for
    the admin and an event's managers; only the routes differ.

      @include('partials.contributor-tables', ['event' => $event, 'tables' => $tables, 'names' => $names,
          'storeUrl' => …, 'updateUrl' => fn ($table) => …, 'destroyUrl' => fn ($table) => …])
--}}
@php($teams = config('contributor_teams'))

<div class="max-w-5xl space-y-6">
    <x-alert type="info">
        Tell attendees where each Contributor Day table is and who leads it. It shows on the <strong>Contribute</strong> tab
        of the app, next to that team. Type leads' names separated by commas: a name that is on the attendee list gets the
        <strong>Table Lead</strong> badge there.
    </x-alert>

    <datalist id="roster-names">
        @foreach ($names as $name)
            <option value="{{ $name }}"></option>
        @endforeach
    </datalist>

    @forelse ($tables as $table)
        <form method="POST" action="{{ $updateUrl($table) }}">
            @csrf
            @method('PUT')
            <x-card :title="$table->teamName()" :description="$table->place() !== '' ? $table->place() : 'Place not set yet'">
                @include('partials.contributor-table-fields', ['table' => $table, 'teams' => $teams, 'prefix' => 'table-'.$table->id])

                <x-slot:footer>
                    <x-button variant="secondary" size="sm">Save</x-button>
                </x-slot:footer>
            </x-card>
        </form>
        <div class="-mt-4 flex justify-end">
            <x-action-form :action="$destroyUrl($table)" method="DELETE" variant="danger-outline" size="sm" icon="trash"
                           :confirm="'Remove the '.$table->teamName().' table?'">Remove table</x-action-form>
        </div>
    @empty
        <x-card>
            <div class="py-6 text-center">
                <x-icon name="inbox" class="mx-auto h-8 w-8 text-muted/50" />
                <p class="mt-2 text-sm font-medium">No tables yet</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Until you add one, the Contribute tab shows the teams without places or leads.</p>
            </div>
        </x-card>
    @endforelse

    <form method="POST" action="{{ $storeUrl }}">
        @csrf
        <x-card title="Add a table" description="One per team table. Everything except the team is optional.">
            @include('partials.contributor-table-fields', ['table' => null, 'teams' => $teams, 'prefix' => 'new-table'])

            <x-slot:footer>
                <x-button icon="plus">Add table</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
