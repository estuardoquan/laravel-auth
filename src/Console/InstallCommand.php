<?php

namespace EQ\Laravel\Auth\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Pest\TestSuite;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

#[AsCommand(name: 'laravel-auth:install')]
class InstallCommand extends Command implements PromptsForMissingInput
{
    use InstallsBladeStack, InstallsInertiaStacks;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel-auth:install {stack : The development stack that should be installed (blade,vue)}
                            {--dark : Indicate that dark mode support should be installed}
                            {--pest : Indicate that Pest should be installed}
                            {--ssr : Indicates if Inertia SSR support should be installed}
                            {--typescript : Indicates if TypeScript is preferred for the Inertia stack}
                            {--eslint : Indicates if ESLint with Prettier should be installed}
                            {--https : Indicates if the Vite dev server should be served over HTTPS}
                            {--container : Indicates if the Vite dev server should be exposed for a containerized environment}
                            {--composer=global : Absolute path to the Composer binary which should be used to install packages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Auth controllers and resources';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        if ($this->argument('stack') === 'vue') {
            return $this->installInertiaVueStack();
        } elseif ($this->argument('stack') === 'blade') {
            return $this->installBladeStack();
        }

        $this->components->error('Invalid stack. Supported stacks are [blade] and [vue].');

        return 1;
    }

    /**
     * Install Auth's tests.
     *
     * @return bool
     */
    protected function installTests()
    {
        (new Filesystem)->ensureDirectoryExists(base_path('tests/Feature'));

        if ($this->option('pest') || $this->isUsingPest()) {
            if ($this->hasComposerPackage('phpunit/phpunit')) {
                $this->removeComposerPackages(['phpunit/phpunit'], true);
            }

            if (! $this->requireComposerPackages(['pestphp/pest', 'pestphp/pest-plugin-laravel'], true)) {
                return false;
            }

            (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/default/pest-tests/Feature', base_path('tests/Feature'));
            (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/default/pest-tests/Unit', base_path('tests/Unit'));
            (new Filesystem)->copy(__DIR__ . '/../../stubs/default/pest-tests/Pest.php', base_path('tests/Pest.php'));
        } else {
            (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/default/tests/Feature', base_path('tests/Feature'));
        }

        return true;
    }

    /**
     * Install the given middleware names into the application.
     *
     * @param  array|string  $name
     * @param  string  $group
     * @param  string  $modifier
     * @return void
     */
    protected function installMiddleware($names, $group = 'web', $modifier = 'append')
    {
        $bootstrapApp = file_get_contents(base_path('bootstrap/app.php'));

        $names = collect(Arr::wrap($names))
            ->filter(fn($name) => ! Str::contains($bootstrapApp, $name))
            ->whenNotEmpty(function ($names) use ($bootstrapApp, $group, $modifier) {
                $names = $names->map(fn($name) => "$name")->implode(',' . PHP_EOL . '            ');

                $stubs = [
                    '->withMiddleware(function (Middleware $middleware) {',
                    '->withMiddleware(function (Middleware $middleware): void {',
                ];

                $bootstrapApp = str_replace(
                    $stubs,
                    collect($stubs)->transform(
                        fn($stub) => $stub
                            . PHP_EOL . "        \$middleware->$group($modifier: ["
                            . PHP_EOL . "            $names,"
                            . PHP_EOL . '        ]);'
                            . PHP_EOL
                    )->all(),
                    $bootstrapApp,
                );

                file_put_contents(base_path('bootstrap/app.php'), $bootstrapApp);
            });
    }

    /**
     * Install the given middleware aliases into the application.
     *
     * @param  array  $aliases
     * @return void
     */
    protected function installMiddlewareAliases($aliases)
    {
        $bootstrapApp = file_get_contents(base_path('bootstrap/app.php'));

        $aliases = collect($aliases)
            ->filter(fn($alias) => ! Str::contains($bootstrapApp, $alias))
            ->whenNotEmpty(function ($aliases) use ($bootstrapApp) {
                $aliases = $aliases->map(fn($name, $alias) => "'$alias' => $name")->implode(',' . PHP_EOL . '            ');

                $stubs = [
                    '->withMiddleware(function (Middleware $middleware) {',
                    '->withMiddleware(function (Middleware $middleware): void {',
                ];

                $bootstrapApp = str_replace(
                    $stubs,
                    collect($stubs)->transform(
                        fn($stub) => $stub
                            . PHP_EOL . '        $middleware->alias(['
                            . PHP_EOL . "            $aliases,"
                            . PHP_EOL . '        ]);'
                            . PHP_EOL
                    )->all(),
                    $bootstrapApp,
                );

                file_put_contents(base_path('bootstrap/app.php'), $bootstrapApp);
            });
    }

    /**
     * Determine if the given Composer package is installed.
     *
     * @param  string  $package
     * @return bool
     */
    protected function hasComposerPackage($package)
    {
        $packages = json_decode(file_get_contents(base_path('composer.json')), true);

        return array_key_exists($package, $packages['require'] ?? [])
            || array_key_exists($package, $packages['require-dev'] ?? []);
    }

    /**
     * Installs the given Composer Packages into the application.
     *
     * @param  bool  $asDev
     * @return bool
     */
    protected function requireComposerPackages(array $packages, $asDev = false)
    {
        $composer = $this->option('composer');

        if ($composer !== 'global') {
            $command = ['php', $composer, 'require'];
        }

        $command = array_merge(
            $command ?? ['composer', 'require'],
            $packages,
            $asDev ? ['--dev'] : [],
        );

        return (new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            }) === 0;
    }

    /**
     * Removes the given Composer Packages from the application.
     *
     * @param  bool  $asDev
     * @return bool
     */
    protected function removeComposerPackages(array $packages, $asDev = false)
    {
        $composer = $this->option('composer');

        if ($composer !== 'global') {
            $command = ['php', $composer, 'remove'];
        }

        $command = array_merge(
            $command ?? ['composer', 'remove'],
            $packages,
            $asDev ? ['--dev'] : [],
        );

        return (new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            }) === 0;
    }

    /**
     * Update the dependencies in the "package.json" file.
     *
     * @param  bool  $dev
     * @return void
     */
    protected static function updateNodePackages(callable $callback, $dev = true)
    {
        if (! file_exists(base_path('package.json'))) {
            return;
        }

        $configurationKey = $dev ? 'devDependencies' : 'dependencies';

        $packages = json_decode(file_get_contents(base_path('package.json')), true);

        $packages[$configurationKey] = $callback(
            array_key_exists($configurationKey, $packages) ? $packages[$configurationKey] : [],
            $configurationKey
        );

        ksort($packages[$configurationKey]);

        file_put_contents(
            base_path('package.json'),
            json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    /**
     * Update the scripts in the "package.json" file.
     *
     * @return void
     */
    protected static function updateNodeScripts(callable $callback)
    {
        if (! file_exists(base_path('package.json'))) {
            return;
        }

        $content = json_decode(file_get_contents(base_path('package.json')), true);

        $content['scripts'] = $callback(
            array_key_exists('scripts', $content) ? $content['scripts'] : []
        );

        file_put_contents(
            base_path('package.json'),
            json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    /**
     * Delete the "node_modules" directory and remove the associated lock files.
     *
     * @return void
     */
    protected static function flushNodeModules()
    {
        tap(new Filesystem, function ($files) {
            $files->deleteDirectory(base_path('node_modules'));

            $files->delete(base_path('pnpm-lock.yaml'));
            $files->delete(base_path('yarn.lock'));
            $files->delete(base_path('bun.lock'));
            $files->delete(base_path('bun.lockb'));
            $files->delete(base_path('deno.lock'));
            $files->delete(base_path('package-lock.json'));
        });
    }

    /**
     * Configure the Vite configuration for HTTPS.
     *
     * @return void
     */
    protected function configureHttps()
    {
        $this->updateNodePackages(function ($packages) {
            return [
                'vite-plugin-https' => 'github:estuardoquan/vite-plugin-https',
            ] + $packages;
        });

        $this->replaceInFile(
            "import { defineConfig } from 'vite';",
            "import { defineConfig } from 'vite';" . PHP_EOL . "import https from 'vite-plugin-https';",
            base_path('vite.config.js')
        );

        $this->replaceInFile(
            'export default defineConfig({',
            'export default defineConfig(async () => ({',
            base_path('vite.config.js')
        );

        $path = $this->option('container')
            ? '            path: process.env.VITECONFIG_HTTPS_PATH,'
            : "            // path: '/var/local/ssl',";

        $this->replaceInFile(
            '    ],' . PHP_EOL . '});',
            '        https({' . PHP_EOL
                . $path . PHP_EOL
                . '        }),' . PHP_EOL
                . '    ],' . PHP_EOL . '}));',
            base_path('vite.config.js')
        );
    }

    /**
     * Configure the Vite configuration for a containerized environment.
     *
     * @return void
     */
    protected function configureContainer()
    {
        $this->replaceInFile(
            '    plugins: [',
            '    server: {' . PHP_EOL
                . '        host: true,' . PHP_EOL
                . '        port: 5173,' . PHP_EOL
                . '        hmr: {' . PHP_EOL
                . '            host: process.env.VITECONFIG_HMR_HOST,' . PHP_EOL
                . '            clientPort: process.env.VITECONFIG_HMR_CLIENTPORT,' . PHP_EOL
                . '        },' . PHP_EOL
                . '    },' . PHP_EOL
                . '    plugins: [',
            base_path('vite.config.js')
        );
    }

    /**
     * Replace a given string within a given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $path
     * @return void
     */
    protected function replaceInFile($search, $replace, $path)
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary()
    {
        if (function_exists('Illuminate\Support\php_binary')) {
            return \Illuminate\Support\php_binary();
        }

        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return void
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        $process->run(function ($type, $line) {
            $this->output->write('    ' . $line);
        });
    }

    /**
     * Remove Tailwind dark classes from the given files.
     *
     * @return void
     */
    protected function removeDarkClasses(Finder $finder)
    {
        foreach ($finder as $file) {
            file_put_contents($file->getPathname(), preg_replace('/\sdark:[^\s"\']+/', '', $file->getContents()));
        }
    }

    /**
     * Prompt for missing input arguments using the returned questions.
     *
     * @return array
     */
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'stack' => fn() => select(
                label: 'Which Auth stack would you like to install?',
                options: [
                    'blade' => 'Blade with Alpine',
                    'vue' => 'Vue with Inertia',
                ],
                scroll: 2,
            ),
        ];
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        $stack = $input->getArgument('stack');

        if ($stack === 'vue') {
            collect(multiselect(
                label: 'Would you like any optional features?',
                options: [
                    'dark' => 'Dark mode',
                    'ssr' => 'Inertia SSR',
                    'typescript' => 'TypeScript',
                    'eslint' => 'ESLint with Prettier',
                    'https' => 'HTTPS dev server',
                    'container' => 'Containerized dev server',
                ],
                hint: 'Use the space bar to select options.'
            ))->each(fn($option) => $input->setOption($option, true));
        } elseif ($stack === 'blade') {
            $input->setOption('dark', confirm(
                label: 'Would you like dark mode support?',
                default: false
            ));

            $input->setOption('https', confirm(
                label: 'Would you like an HTTPS dev server?',
                default: false
            ));

            $input->setOption('container', confirm(
                label: 'Would you like a containerized dev server?',
                default: false
            ));
        }

        $input->setOption('pest', select(
            label: 'Which testing framework do you prefer?',
            options: ['Pest', 'PHPUnit'],
            default: 'Pest',
        ) === 'Pest');
    }

    /**
     * Determine whether the project is already using Pest.
     *
     * @return bool
     */
    protected function isUsingPest()
    {
        return class_exists(TestSuite::class);
    }
}
