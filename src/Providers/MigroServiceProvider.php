<?php

namespace Savks\Migro\Providers;

use Illuminate\Support\ServiceProvider;
use Savks\Migro\Support\Manager;
use Savks\Migro\Commands;

class MigroServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->app->singleton('migro', Manager::class);

        $this->registerCommands();
        
        $this->registerConfigsPublish();
    }

    /**
     * @return void
     */
    protected function registerCommands(): void
    {
        $this->commands([
            Commands\Install::class,
            Commands\Create::class,
            Commands\Migrate::class,
            Commands\Rollback::class,
            Commands\Status::class,
        ]);
    }

    /**
     * @return void
     */
    protected function registerConfigsPublish(): void
    {
        $config = \dirname(__DIR__, 2) . '/resources/configs/migro.php';

        $this->publishes(
            [
                $config => config_path('migro.php'),
            ],
            'configs'
        );

        $this->mergeConfigFrom($config, 'migro');
    }
}
