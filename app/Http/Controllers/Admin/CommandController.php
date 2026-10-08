<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Admin → Commands: every CampBuddy artisan command (App\Console\Commands —
 * new ones show up by themselves), what it does and its options, with a Run
 * form; then the safe Laravel maintenance commands (MAINTENANCE), also
 * runnable; then every other artisan command, listed for reference only. A run happens in this request (no queue): the output comes back on
 * the page and the run is logged with who started it. One run at a time.
 * Prompts can't be answered from a browser, so --yes is set where a command
 * has it; the page asks first instead. campbuddy:doctor is terminal-only
 * (migrations, npm build, emptying the logs).
 */
class CommandController extends Controller
{
    /** Shown, but only runnable from the server's terminal. */
    public const TERMINAL_ONLY = ['campbuddy:doctor'];

    /**
     * Laravel's own commands that are safe to run from here: clearing caches,
     * looking at the queue, the routes, the schedule. Every other framework
     * command is listed for reference only (migrate:fresh, db:wipe, make:*…).
     */
    public const MAINTENANCE = [
        'about', 'optimize:clear', 'cache:clear', 'config:clear', 'route:clear', 'view:clear',
        'queue:failed', 'queue:retry', 'queue:flush', 'migrate:status', 'route:list', 'schedule:list',
    ];

    /** Long fetches can outlive the browser's wait (Cloudflare gives up at 100 s); they finish anyway. */
    private const TIME_LIMIT = 600;

    public function index(): View
    {
        $commands = collect($this->commands());

        return view('admin.commands.index', [
            'commands' => $commands->where('group', 'campbuddy')->all(),
            'maintenance' => $commands->where('group', 'maintenance')->sortBy(fn ($c) => array_search($c['name'], self::MAINTENANCE, true))->all(),
            'others' => $commands->where('group', 'other')->all(),
        ]);
    }

    public function run(Request $request, string $name): RedirectResponse
    {
        $command = $this->commands()[$name] ?? null;
        abort_if($command === null, 404);
        abort_if(! $command['runnable'], 403, 'This command runs only from the server terminal.');

        $params = ['--no-interaction' => true];

        foreach ($command['arguments'] as $argument) {
            $value = trim((string) $request->input('arg_'.$argument['name'], ''));
            if ($value === '' && $argument['required']) {
                return back()->with('warning', "{$name}: \"{$argument['name']}\" is required.");
            }
            if ($value !== '') {
                $value = $this->clean($value);
                $params[$argument['name']] = $argument['array'] ? preg_split('/\s+/', $value) : $value;
            }
        }

        foreach ($command['options'] as $option) {
            $field = 'opt_'.$option['name'];
            if ($option['flag']) {
                if ($request->boolean($field)) {
                    $params['--'.$option['name']] = true;
                }
            } elseif (($value = trim((string) $request->input($field, ''))) !== '') {
                $value = $this->clean($value);
                $params['--'.$option['name']] = $option['array'] ? preg_split('/\s+/', $value) : $value;
            }
        }

        if ($command['hasYes']) {
            $params['--yes'] = true;
        }

        $lock = Cache::lock('admin-command-run', self::TIME_LIMIT);
        if (! $lock->get()) {
            return back()->with('warning', 'Another command is running. Try again in a minute.');
        }

        $line = $this->commandLine($name, $params);
        Log::info('Admin ran a command', ['command' => $line, 'user_id' => $request->user()->id]);

        $output = new BufferedOutput;
        $started = microtime(true);

        try {
            @set_time_limit(self::TIME_LIMIT);
            ignore_user_abort(true);
            $exit = app(Kernel::class)->call($name, $params, $output);
            $text = $output->fetch();
        } catch (\Throwable $e) {
            $exit = 1;
            $text = $output->fetch()."\n".$e->getMessage();
            Log::warning('Admin command failed', ['command' => $line, 'error' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.commands.index')->with('commandRun', [
            'name' => $name,
            'line' => $line,
            'exit' => $exit,
            'seconds' => round(microtime(true) - $started, 1),
            'output' => trim(preg_replace('/\e\[[\d;]*m/', '', $text)) ?: '(no output)',
        ])->withFragment('cmd-'.str_replace(':', '-', $name));
    }

    /**
     * Every artisan command, by name: description, arguments, options, and
     * its group: "campbuddy" (App\Console\Commands), "maintenance" (the safe
     * Laravel ones above) or "other" (listed only, never run from here).
     *
     * @return array<string, array{name: string, description: string, arguments: array, options: array, hasYes: bool, group: string, runnable: bool}>
     */
    private function commands(): array
    {
        return collect(app(Kernel::class)->all())
            ->reject(fn (SymfonyCommand $command) => $command->isHidden())
            ->map(function (SymfonyCommand $command) {
                $group = match (true) {
                    $command instanceof Command && str_starts_with($command::class, 'App\\Console\\Commands\\') => 'campbuddy',
                    in_array($command->getName(), self::MAINTENANCE, true) => 'maintenance',
                    default => 'other',
                };

                $definition = $command->getDefinition();

                $options = collect($definition->getOptions())
                    ->reject(fn (InputOption $o) => $o->getName() === 'yes' || in_array($o->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'], true))
                    ->map(fn (InputOption $o) => [
                        'name' => $o->getName(),
                        'description' => $o->getDescription(),
                        'flag' => ! $o->acceptValue(),
                        'array' => $o->isArray(),
                        'default' => $o->acceptValue() && is_scalar($o->getDefault()) ? (string) $o->getDefault() : null,
                    ])->values()->all();

                return [
                    'name' => $command->getName(),
                    'description' => $command->getDescription(),
                    'arguments' => collect($definition->getArguments())
                        ->reject(fn (InputArgument $a) => $a->getName() === 'command')
                        ->map(fn (InputArgument $a) => ['name' => $a->getName(), 'description' => $a->getDescription(), 'required' => $a->isRequired(), 'array' => $a->isArray()])
                        ->values()->all(),
                    'options' => $options,
                    'hasYes' => $definition->hasOption('yes'),
                    'group' => $group,
                    'runnable' => $group !== 'other' && ! in_array($command->getName(), self::TERMINAL_ONLY, true),
                ];
            })
            ->sortKeys()
            ->all();
    }

    /** One line, no control characters: these go straight into a command's input. */
    private function clean(string $value): string
    {
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $value), 0, 200);
    }

    private function commandLine(string $name, array $params): string
    {
        $parts = ['php artisan', $name];

        foreach ($params as $key => $value) {
            if ($key === '--no-interaction') {
                continue;
            }
            $quote = fn (string $v) => preg_match('/^[\w.:\/@-]+$/u', $v) ? $v : '"'.str_replace('"', '\\"', $v).'"';

            if ($value === true) {
                $parts[] = $key;
            } elseif (is_array($value)) {
                foreach ($value as $one) {
                    $parts[] = str_starts_with($key, '--') ? $key.'='.$quote($one) : $quote($one);
                }
            } else {
                $parts[] = str_starts_with($key, '--') ? $key.'='.$quote($value) : $quote($value);
            }
        }

        return implode(' ', $parts);
    }
}
