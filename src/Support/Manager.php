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
     * @param string|null $tag
     * @return array
     */
    public function collectFilePaths(string $tag = null): array
    {
        $paths = $this->app['config']->get('migro.paths');

        $filePaths = [];

        foreach ($paths as $path) {
            $path = ! Str::endsWith($path, '.php') ?
                rtrim($path, '/') . '/*.php' :
                $path;

            foreach (glob($path) as $file) {
                $filename = basename($file);

                if (! Helpers::isMigrationName($filename)) {
                    continue;
                }

                if ($tag) {
                    if (! Str::startsWith($filename, "{$tag}.")) {
                        continue;
                    }
                } else {
                    $filenameParts = explode('.', $filename);

                    if (count($filenameParts) !== 2) {
                        continue;
                    }
                }

                $filePaths[] = $file;
            }
        }

        return $filePaths;
    }

    /**
     * @return string
     */
    public function table(): string
    {
        return $this->app['config']->get('migro.table', 'migro');
    }
}
