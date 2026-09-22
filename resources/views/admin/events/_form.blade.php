@php($isEdit = $event->exists)

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
        <x-input-label for="slug" value="Slug" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full"
                      :value="old('slug', $event->slug)" required autofocus placeholder="wordcamp-rajasthan-2026" />
        <x-input-error :messages="$errors->get('slug')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="display_name" value="Display name" />
        <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full"
                      :value="old('display_name', $event->display_name)" required placeholder="WordCamp Rajasthan 2026" />
        <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="short_name" value="Short name / hashtag" />
        <x-text-input id="short_name" name="short_name" type="text" class="mt-1 block w-full"
                      :value="old('short_name', $event->short_name)" placeholder="#WCRajasthan" />
        <x-input-error :messages="$errors->get('short_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="source_site_url" value="Source site URL" />
        <x-text-input id="source_site_url" name="source_site_url" type="url" class="mt-1 block w-full"
                      :value="old('source_site_url', $event->source_site_url)" required placeholder="https://rajasthan.wordcamp.org/2026" />
        <x-input-error :messages="$errors->get('source_site_url')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="primary_color" value="Primary color (§3.2 BR7 — WCAG AA checked)" />
        <input id="primary_color" name="primary_color" type="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300"
               value="{{ old('primary_color', $event->primary_color ?? '#1a2b3c') }}" />
        <x-input-error :messages="$errors->get('primary_color')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="accent_color" value="Accent color (§3.2 BR7 — WCAG AA checked)" />
        <input id="accent_color" name="accent_color" type="color" class="mt-1 block h-10 w-20 rounded-md border-gray-300"
               value="{{ old('accent_color', $event->accent_color ?? '#1a2b3c') }}" />
        <x-input-error :messages="$errors->get('accent_color')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="status" value="Lifecycle status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @foreach (['draft', 'approved', 'active', 'archived'] as $status)
                <option value="{{ $status }}" @selected(old('status', $event->status) === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div class="flex items-center mt-6">
        <label class="inline-flex items-center">
            <input type="hidden" name="is_visible" value="0">
            <input type="checkbox" name="is_visible" value="1" class="rounded border-gray-300 text-indigo-600"
                   @checked(old('is_visible', $event->is_visible ?? true))>
            <span class="ms-2 text-sm text-gray-700">Visible in the public app (§3.2 BR6 — default on)</span>
        </label>
    </div>
</div>
