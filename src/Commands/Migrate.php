<?php

namespace Savks\Migro\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Grammars\Grammar;
use Savks\Migro\Support\Helpers;
use Savks\Migro\Support\Manifest;
use Savks\Migro\Support\Step;
use Schema;
use Throwable;

class Migrate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migro:migrate {table?} {--tag=} {--steps=} {--connection=}';

    /**
     * @var string
     */
    protected $description = 'Migrate all registered migrations';

    /**
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $table = $this->argument('table');

        $tag = $this->option('tag');
        $stepsLimit = $this->option('steps');

        $filePaths = app('migro')->collectFilePaths($tag);

        if (! $filePaths) {
            $this->warn('Nothing to migrate...');

            return self::SUCCESS;
        }

        $migrationsTable = app('migro')->table();

        $lastBatch = $this->connection()->table($migrationsTable)->max('batch');

        $currentBatch = $lastBatch ? $lastBatch + 1 : 1;
        
        $stepsCount = 0;

        foreach (Helpers::filterMigrationFilePaths($filePaths, $table, $tag) as $filePath) {
            $stepsCount += $this->processFilePath($filePath, $currentBatch, $stepsLimit);
        }
        
        if (! $stepsCount) {
            $this->warn('Nothing to migrate...');
        }

        return self::SUCCESS;
    }

    /**
     * @param string $path
     * @param int $batch
     * @param int|null $stepsLimit
     * @return int
     * @throws Throwable
     */
    protected function processFilePath(string $path, int $batch, int $stepsLimit = null): int
    {
        [$table, $tag] = Helpers::parseMigrationPath($path);

        $manifest = $this->makeManifest($table, $tag, $path);

        $name = $manifest->tag() ? "[{$manifest->tag()}]{$manifest->table()}" : $manifest->table();

        $startTime = microtime(true);

        $lastStep = $this
            ->connection()
            ->table(
                app('migro')->table()
            )
            ->where('table', '=', $table)
            ->when(
                $tag,
                function (Builder $query, string $tag) {
                    $query->where('tag', '=', $tag);
                }
            )
            ->max('step');

        $steps = array_slice(
            $manifest->steps(),
            $lastStep ?: 0,
            $stepsLimit
        );

        if (! $steps) {
            return 0;
        }

        $this->getOutput()->writeln("<comment>Migrating:</comment> {$name}");

        foreach ($steps as $index => $step) {
            $this->upStep($manifest, $step);

            $query = $this->connection()->table(
                app('migro')->table()
            );

            $query->insert([
                'table' => $manifest->table(),
                'tag' => $manifest->tag() ?: null,
                'step' => $step->number,
                'batch' => $batch,
            ]);
        }

        $runTime = number_format((microtime(true) - $startTime) * 1000, 2);

        $this->getOutput()->writeln("<info>Migrated:</info>  {$name} ({$runTime}ms)");
        
        return count($steps);
    }

    /**
     * @return Connection
     */
    protected function connection(): Connection
    {
        return DB::connection(
            $this->option('connection')
        );
    }

    /**
     * @param string $table
     * @param string|null $tag
     * @param string $path
     * @return Manifest
     */
    protected function makeManifest(string $table, ?string $tag, string $path): Manifest
    {
        $manifest = new Manifest($table, $tag);

        require $path;

        return $manifest;
    }

    /**
     * @param Manifest $manifest
     * @param Step $step
     * @throws Throwable
     */
    protected function upStep(Manifest $manifest, Step $step): void
    {
        $this->getOutput()->writeln("Step: {$step->number}");

        $callback = function () use ($manifest, $step) {
            $table = $manifest->table();

            switch ($step->type) {
                case $step::CREATE:
                    Schema::create($table, $step->up);
                    break;
                case $step::MODIFY:
                    Schema::table($table, $step->up);
                    break;
                case $step::RAW:
                    call_user_func($step->up, $manifest, $step);
                    break;

                default:
                    throw new \RuntimeException("Invalid migration step type \"{$step->type}\"");
            }
        };

        if (! $step->withoutTransaction
            && $this->schemaGrammar()->supportsSchemaTransactions()
        ) {
            $this->connection()->transaction($callback);
        } else {
            $callback();
        }
    }

    /**
     * @return Grammar
     */
    protected function schemaGrammar(): Grammar
    {
        $grammar = $this->connection()->getSchemaGrammar();

        if ($grammar === null) {
            $this->connection()->useDefaultSchemaGrammar();

            $grammar = $this->connection()->getSchemaGrammar();
        }

        return $grammar;
    }
}
