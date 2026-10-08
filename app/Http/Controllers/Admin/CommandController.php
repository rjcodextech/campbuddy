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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Admin → Commands: every CampBuddy artisan command (App\Console\Commands —
 * new ones show up by themselves), what it does and its options, with a Run
 * form. A run happens in this request (no queue): the output comes back on
 * the page and the run is logged with who started it. One run at a time.
 * Prompts can't be answered from a browser, so --yes is set where a command
 * has it; the page asks first instead. campbuddy:doctor is terminal-only
 * (migrations, npm build, emptying the logs).
 */
class CommandController extends Controller
{
    /** Shown, but only runnable from the server's terminal. */
    public const TERMINAL_ONLY = ['campbuddy:doctor'];

    /** Long fetches can outlive the browser's wait (Cloudflare gives up at 100 s); they finish anyway. */
    private const TIME_LIMIT = 600;

    public function index(): View
    {
        return view('admin.commands.index', ['commands' => $this->commands()]);
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
                $params[$argument['name']] = $this->clean($value);
            }
        }

        foreach ($command['options'] as $option) {
            $field = 'opt_'.$option['name'];
            if ($option['flag']) {
                if ($request->boolean($field)) {
                    $params['--'.$option['name']] = true;
                }
            } elseif (($value = trim((string) $request->input($field, ''))) !== '') {
                $params['--'.$option['name']] = $this->clean($value);
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
     * The app's own commands, by name: description, arguments, options.
     *
     * @return array<string, array{name: string, description: string, arguments: array, options: array, hasYes: bool, runnable: bool}>
     */
    private function commands(): array
    {
        return collect(app(Kernel::class)->all())
            ->filter(fn ($command) => $command instanceof Command && str_starts_with($command::class, 'App\\Console\\Commands\\'))
            ->map(function (Command $command) {
                $definition = $command->getDefinition();

                $options = collect($definition->getOptions())
                    ->reject(fn (InputOption $o) => $o->getName() === 'yes' || in_array($o->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'], true))
                    ->map(fn (InputOption $o) => [
                        'name' => $o->getName(),
                        'description' => $o->getDescription(),
                        'flag' => ! $o->acceptValue(),
                        'default' => $o->acceptValue() && is_scalar($o->getDefault()) ? (string) $o->getDefault() : null,
                    ])->values()->all();

                return [
                    'name' => $command->getName(),
                    'description' => $command->getDescription(),
                    'arguments' => collect($definition->getArguments())
                        ->reject(fn (InputArgument $a) => $a->getName() === 'command')
                        ->map(fn (InputArgument $a) => ['name' => $a->getName(), 'description' => $a->getDescription(), 'required' => $a->isRequired()])
                        ->values()->all(),
                    'options' => $options,
                    'hasYes' => $definition->hasOption('yes'),
                    'runnable' => ! in_array($command->getName(), self::TERMINAL_ONLY, true),
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
            $value = $value === true ? null : (preg_match('/^[\w.:\/@-]+$/u', $value) ? $value : '"'.str_replace('"', '\\"', $value).'"');
            $parts[] = str_starts_with($key, '--') ? $key.($value === null ? '' : '='.$value) : $value;
        }

        return implode(' ', $parts);
    }
}
