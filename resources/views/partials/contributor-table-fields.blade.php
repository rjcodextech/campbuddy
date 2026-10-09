{{-- The fields of one Contributor Day table (partials/contributor-tables.blade.php). --}}
<div class="grid gap-4 md:grid-cols-3">
    <div>
        <label for="{{ $prefix }}-team" class="cb-label">Team</label>
        <select id="{{ $prefix }}-team" name="team" class="cb-input" required>
            @foreach ($teams as $key => $label)
                <option value="{{ $key }}" @selected(($table?->team ?? 'core') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $prefix }}-title" class="cb-label">Title <span class="font-normal text-muted">(needed for "Other")</span></label>
        <input id="{{ $prefix }}-title" name="title" type="text" maxlength="80" value="{{ $table?->title }}" placeholder="e.g. Hindi translations" class="cb-input">
    </div>
    <div>
        <label for="{{ $prefix }}-track" class="cb-label">Track / room</label>
        <input id="{{ $prefix }}-track" name="track" type="text" maxlength="80" value="{{ $table?->track }}" placeholder="e.g. Hall B" class="cb-input">
    </div>
    <div>
        <label for="{{ $prefix }}-floor" class="cb-label">Floor</label>
        <input id="{{ $prefix }}-floor" name="floor" type="text" maxlength="40" value="{{ $table?->floor }}" placeholder="e.g. 2" class="cb-input">
    </div>
    <div>
        <label for="{{ $prefix }}-table-no" class="cb-label">Table number</label>
        <input id="{{ $prefix }}-table-no" name="table_no" type="text" maxlength="20" value="{{ $table?->table_no }}" placeholder="e.g. 5" class="cb-input">
    </div>
    <div>
        <label for="{{ $prefix }}-order" class="cb-label">Order</label>
        <input id="{{ $prefix }}-order" name="sort_order" type="number" min="0" max="9999" value="{{ $table?->sort_order ?? 0 }}" class="cb-input">
    </div>
    <div class="md:col-span-2">
        <label for="{{ $prefix }}-leads" class="cb-label">Table leads</label>
        <input id="{{ $prefix }}-leads" name="leads" type="text" maxlength="500" list="roster-names" value="{{ $table ? implode(', ', $table->leadNames()) : '' }}" placeholder="Names, separated by commas" class="cb-input">
    </div>
    <div>
        <label for="{{ $prefix }}-note" class="cb-label">Note</label>
        <input id="{{ $prefix }}-note" name="note" type="text" maxlength="300" value="{{ $table?->note }}" placeholder="e.g. Bring a laptop" class="cb-input">
    </div>
</div>
