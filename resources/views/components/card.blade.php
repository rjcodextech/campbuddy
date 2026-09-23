{{--
    A titled surface — every group of related fields/content on an admin
    page lives in one of these.

      <x-card title="Branding" description="Logo and favicon shown in the app.">
          …
          <x-slot:actions><x-button size="sm">…</x-button></x-slot:actions>
          <x-slot:footer>…</x-slot:footer>
      </x-card>

    danger: red outline for destructive zones. flush: no body padding (tables).
--}}
@props([
    'title' => null,
    'description' => null,
    'danger' => false,
    'flush' => false,
])

<section {{ $attributes->class([
    'overflow-hidden rounded-xl border bg-white shadow-sm',
    'border-line' => ! $danger,
    'border-danger/30' => $danger,
]) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4 sm:px-6 {{ $danger ? 'border-danger/20 bg-danger-soft' : 'border-line' }}">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold {{ $danger ? 'text-danger-dark' : 'text-ink' }}">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['px-5 py-5 sm:px-6' => ! $flush])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-3 border-t border-line bg-paper/60 px-5 py-3 sm:px-6">
            {{ $footer }}
        </footer>
    @endisset
</section>
