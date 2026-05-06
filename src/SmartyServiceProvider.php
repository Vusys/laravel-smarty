<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Foundation\Exceptions\Renderer\Mappers\BladeMapper;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Vusys\LaravelSmarty\Debug\SmartyExceptionMapper;

class SmartyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fail loud at boot if Smarty has refactored Template::$compiler out
        // from under BridgedSmarty's reflection injection.
        BridgedSmarty::ensureCompilerInjectionAvailable();

        $this->mergeConfigFrom(__DIR__.'/../config/smarty.php', 'smarty');

        $this->app->singleton(SmartyFactory::class, fn ($app) => new SmartyFactory($app->make('files'), $app->make('config')->get('smarty')));

        // The view engine resolver is bound by Laravel as a string-keyed
        // singleton. Alias the class so DI consumers (notably the artisan
        // commands) get the same instance our boot() registered "smarty" on,
        // not a fresh empty resolver auto-built by the container.
        $this->app->alias('view.engine.resolver', EngineResolver::class);

        // Laravel's exception Renderer constructs BladeMapper directly. Rebind
        // it to our subclass so the same compiled-→-source frame rewriting
        // Blade enjoys also applies to Smarty's .tpl.php compiled files.
        if (class_exists(BladeMapper::class)) {
            $this->app->bind(BladeMapper::class, SmartyExceptionMapper::class);
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/smarty.php' => $this->app->configPath('smarty.php'),
        ], 'smarty-config');

        $this->registerPaginationViews();

        $this->registerEngine();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCacheCommand::class,
                Console\ClearCompiledCommand::class,
                Console\OptimizeCommand::class,
            ]);
        }
    }

    /**
     * Register the package's Smarty pagination templates so they win over
     * Laravel's framework Blade pagination views for the same `pagination::*`
     * names. `loadViewsFrom` appends, which would leave Laravel's Blade
     * variants resolving first; we prepend instead so a `.tpl` lookup hits
     * our directory before the framework one. User-published vendor
     * overrides (`resources/views/vendor/pagination`) are prepended last so
     * they sit ahead of both.
     */
    protected function registerPaginationViews(): void
    {
        $packagePath = __DIR__.'/../resources/views/pagination';

        $this->callAfterResolving('view', function (ViewFactory $view) use ($packagePath): void {
            $view->prependNamespace('pagination', $packagePath);

            $viewPathsRaw = $this->app->make('config')->get('view.paths', []);
            $viewPaths = is_array($viewPathsRaw) ? array_filter($viewPathsRaw, is_string(...)) : [];

            foreach ($viewPaths as $base) {
                $vendorPath = $base.'/vendor/pagination';
                if (is_dir($vendorPath)) {
                    $view->prependNamespace('pagination', $vendorPath);
                }
            }
        });

        $this->publishes([
            $packagePath => $this->app->resourcePath('views/vendor/pagination'),
        ], 'smarty-pagination-views');
    }

    protected function registerEngine(): void
    {
        $extensionRaw = $this->app->make('config')->get('smarty.extension', 'tpl');
        $extension = is_string($extensionRaw) ? $extensionRaw : 'tpl';

        $this->app->make('view.engine.resolver')->register('smarty', function () use ($extension) {
            $smartyFactory = $this->app->make(SmartyFactory::class);
            $pathsRaw = $this->app->make('config')->get('view.paths', []);
            $paths = is_array($pathsRaw) ? array_values(array_filter($pathsRaw, is_string(...))) : [];
            $smarty = $smartyFactory->make($paths);

            $engine = new SmartyEngine($smarty, $this->app->make('files'));

            /** @var ViewFactory $viewFactory */
            $viewFactory = $this->app->make(ViewFactoryContract::class);
            $resource = new SmartyResource(
                $viewFactory,
                $this->app->make('events'),
                $engine,
                $extension,
            );

            $smarty->setResource($resource);

            return $engine;
        });

        /** @var ViewFactory $view */
        $view = $this->app->make(ViewFactoryContract::class);
        $view->addExtension($extension, 'smarty');
    }
}
