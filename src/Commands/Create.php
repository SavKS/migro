<?php

namespace Savks\Migro\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Filesystem\Filesystem;

class Create extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migro:create {table} {--tag=} {--path=}';

    /**
     * @var string
     */
    protected $description = 'Create migration';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Create constructor.
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
    }


    /**
     * @return int
     */
    public function handle(): int
    {
        $path = rtrim(
            $this->option('path') ?: database_path('migro'),
            '/'
        );

        $filename = $this->argument('table');

        if ($tag = $this->option('tag')) {
            if (preg_match('/^[\w-]+$/', $tag) === 0) {
                $this->error('Invalid tag name');

                return self::FAILURE;
            }

            $filename = "{$tag}.{$filename}";
        }

        if ($this->filesystem->exists("{$path}/{$filename}.php")) {
            $this->warn("Migration \"{$path}/{$filename}.php\" already exists");

            return self::FAILURE;
        }

        $this->filesystem->dumpFile("{$path}/{$filename}.php", '');

        $this->info("Migration created: {$path}/{$filename}.php");

        return self::SUCCESS;
    }
}
