<?php

namespace Vusys\LaravelSmarty;

use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;

class SmartyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/smarty.php', 'smarty');

        $this->app->singleton(SmartyFactory::class, fn($app) => new SmartyFactory($app['files'], $app['config']->get('smarty')));

        // The view engine resolver is bound by Laravel as a string-keyed
        // singleton. Alias the class so DI consumers (notably the artisan
        // commands) get the same instance our boot() registered "smarty" on,
        // not a fresh empty resolver auto-built by the container.
        $this->app->alias('view.engine.resolver', EngineResolver::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/smarty.php' => $this->app->configPath('smarty.php'),
        ], 'smarty-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views/pagination', 'pagination');

        $this->registerEngine();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCacheCommand::class,
                Console\ClearCompiledCommand::class,
                Console\OptimizeCommand::class,
            ]);
        }
    }

    protected function registerEngine(): void
    {
        $extension = $this->app['config']->get('smarty.extension', 'tpl');

        $this->app['view.engine.resolver']->register('smarty', function () use ($extension) {
            $smartyFactory = $this->app->make(SmartyFactory::class);
            $paths = $this->app['config']->get('view.paths', []);
            $smarty = $smartyFactory->make($paths);

            $engine = new SmartyEngine($smarty, $this->app['files']);

            /** @var ViewFactory $viewFactory */
            $viewFactory = $this->app->make(ViewFactoryContract::class);
            $resource = new SmartyResource(
                $viewFactory,
                $this->app['events'],
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
