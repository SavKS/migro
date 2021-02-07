<?php

namespace Savks\Migro\Support;

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

class Manager
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $customPaths = [];

    /**
     * Manager constructor.
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function registerPath(string $path): Manager
    {
        if (! in_array($path, $this->customPaths, true)) {
            $this->customPaths[] = $path;
        }

        return $this;
    }

    /**
     * @return FilesRepository|File[]
     */
    public function collectFiles(): FilesRepository
    {
        $paths = array_merge(
            $this->app['config']->get('migro.paths', []),
            $this->customPaths
        );

        $filePaths = [];

        foreach ($paths as $path) {
            $path = ! Str::endsWith($path, '.php') ?
                rtrim($path, '/') . '/*.php' :
                $path;

            $filePaths[] = glob($path);
        }

        $result = array_values(
            array_unique(
                $filePaths ? array_merge(...$filePaths) : []
            )
        );

        return FilesRepository::from($result);
    }

    /**
     * @return string
     */
    public function table(): string
    {
        return $this->app['config']->get('migro.table', 'migro');
    }
}
