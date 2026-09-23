{{--
    A responsive data table. Put <th> cells in the `head` slot and plain
    <tr>/<td> rows in the body; the .cb-table styles (resources/css/app.css)
    do the rest. Scrolls sideways on small screens instead of breaking.

      <x-table>
          <x-slot:head><th>Name</th><th>Status</th></x-slot:head>
          @forelse (…) <tr><td>…</td></tr> @empty <x-table.empty :colspan="2">…</x-table.empty> @endforelse
      </x-table>
--}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="cb-table">
        <thead>
            <tr>{{ $head }}</tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
