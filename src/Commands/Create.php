<?php

namespace Savks\Migro\Commands;

use Illuminate\Console\Command;
use Savks\Consoler\Consoler;
use Symfony\Component\Filesystem\Filesystem;

class Create extends BaseCommand
{
    /**
     * @var string
     */
    protected $signature = 'migro:create {table : The migration table name}
                {--tag= : The tag of the migration}
                {--path= : The location where the migration file should be created}';

    /**
     * @var string
     */
    protected $description = 'Create a new migration file';

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

        $table = $this->argument('table');
        $tag = $this->option('tag');

        $files = app('migro')->collectFiles()->forTable($table);

        if ($tag) {
            if (preg_match('/^[\w-]+$/', $tag) === 0) {
                $this->error('Invalid tag name');

                return self::FAILURE;
            }

            $files = $files->taggedAs($tag);
        } else {
            $files = $files->withoutTags();
        }

        $filename = $this->resolveFilename($table, $tag);

        if ($files->isNotEmpty()) {
            $this->warn("Migration \"{$filename}\" already exists in \"{$files->first()->path}\"");

            return self::FAILURE;
        }

        $stubFilenamePath = sprintf(
            '%s/resources/stubs/%s',
            dirname(__DIR__, 2),
            $tag ? 'modify.stub' : 'create.stub'
        );

        $this->filesystem->dumpFile(
            "{$path}/{$filename}.php",
            file_get_contents($stubFilenamePath)
        );

        $this->info("Migration created: {$path}/{$filename}.php");

        return self::SUCCESS;
    }
}
