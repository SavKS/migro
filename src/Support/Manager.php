<?php

namespace Savks\Migro\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

class Manager
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Manager constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return FilesRepository|File[]
     */
    public function collectFiles(): FilesRepository
    {
        $paths = $this->app['config']->get('migro.paths');

        $filePaths = [];

        foreach ($paths as $path) {
            $path = ! Str::endsWith($path, '.php') ?
                rtrim($path, '/') . '/*.php' :
                $path;

            $filePaths[] = glob($path);
        }

        return FilesRepository::from(
            $filePaths ? array_merge(...$filePaths) : []
        );
    }

    /**
     * @return string
     */
    public function table(): string
    {
        return $this->app['config']->get('migro.table', 'migro');
    }
}
