<x-app-layout title="Commands" subtitle="CampBuddy's own artisan commands: what each one does, and a form to run it. A run happens right here and its output shows below it."
              :breadcrumbs="[['Commands']]">
    @php($last = session('commandRun'))

    <x-alert type="warning" class="mb-6" title="Runs on the live site">
        Commands that send pushes reach real phones. Tick <strong>dry-run</strong> first where it's offered to see what would happen.
        One command runs at a time; a long one keeps going on the server even if this page stops waiting.
    </x-alert>

    <div class="space-y-6">
        @foreach ($commands as $command)
            @php($anchor = 'cmd-'.str_replace(':', '-', $command['name']))
            <x-card id="{{ $anchor }}" :title="$command['name']" :description="$command['description']">
                <x-slot:actions>
                    @if (! $command['runnable'])
                        <x-badge variant="neutral">Terminal only</x-badge>
                    @elseif ($command['hasYes'])
                        <x-badge variant="warning">Sends without asking</x-badge>
                    @endif
                </x-slot:actions>

                <p class="mb-4 overflow-x-auto rounded-md bg-paper-soft px-3 py-2 font-mono text-xs text-ink">php artisan {{ $command['name'] }}@foreach ($command['arguments'] as $argument) &lt;{{ $argument['name'] }}&gt;@endforeach @foreach ($command['options'] as $option)[--{{ $option['name'] }}{{ $option['flag'] ? '' : '=' }}] @endforeach</p>

                @if ($command['runnable'])
                    <form method="POST" action="{{ route('admin.commands.run', $command['name']) }}"
                          x-data x-on:submit="if (! confirm(@js('Run '.$command['name'].' on the live site now?'.($command['hasYes'] ? ' It will not ask again before sending.' : '')))) $event.preventDefault()">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach ($command['arguments'] as $argument)
                                <x-form.input :id="$anchor.'-arg-'.$argument['name']" :name="'arg_'.$argument['name']" :label="$argument['name']"
                                              :hint="$argument['description']" :required="$argument['required']" :use-old="false" :show-error="false" />
                            @endforeach
                            @foreach ($command['options'] as $option)
                                @if ($option['flag'])
                                    <x-form.checkbox :id="$anchor.'-opt-'.$option['name']" :name="'opt_'.$option['name']" :label="'--'.$option['name']"
                                                     :hint="$option['description']" :use-old="false" :show-error="false" />
                                @else
                                    <x-form.input :id="$anchor.'-opt-'.$option['name']" :name="'opt_'.$option['name']" :label="'--'.$option['name']"
                                                  :hint="$option['description'].($option['default'] !== null ? ' (default '.$option['default'].')' : '')"
                                                  :placeholder="$option['default']" :use-old="false" :show-error="false" />
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <x-button size="sm" icon="refresh">Run</x-button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-muted">Run this from the server terminal (cPanel → Terminal or SSH). See DEPLOYMENT.md.</p>
                @endif

                @if ($last && $last['name'] === $command['name'])
                    <div class="mt-5 border-t border-line pt-4">
                        <p class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                            <x-badge :variant="$last['exit'] === 0 ? 'success' : 'danger'">{{ $last['exit'] === 0 ? 'Done' : 'Failed (exit '.$last['exit'].')' }}</x-badge>
                            <span class="text-muted">{{ $last['seconds'] }} s</span>
                            <code class="font-mono text-xs text-ink">{{ $last['line'] }}</code>
                        </p>
                        <pre class="max-h-96 overflow-auto rounded-md bg-ink px-4 py-3 font-mono text-xs leading-relaxed text-white">{{ $last['output'] }}</pre>
                    </div>
                @endif
            </x-card>
        @endforeach
    </div>
</x-app-layout>
