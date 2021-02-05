<?php

namespace Savks\Migro\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Collection;
use Savks\Migro\Support\Manifest;
use Savks\Migro\Support\Step;
use Schema;
use Throwable;

class Rollback extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migro:rollback {table?} {--tag=} {--steps=} {--batches=} {--connection=}';

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
        $batches = $this->option('batches');

        $filesRepository = app('migro')->collectFiles();

        $records = $this->resolveRecords($table, $tag, $batches);

        if ($records->isEmpty()) {
            $this->warn('Nothing to rollback...');

            return self::SUCCESS;
        }

        dd($records);

        foreach ($records as $file) {
            $stepsCount += $this->processRecord(
                $currentBatch,
                $stepsLimit
            );
        }

        if (! $stepsCount) {
            $this->warn('Nothing to migrate...');
        }

        return self::SUCCESS;
    }

    /**
     * @param Manifest $manifest
     * @param int $batch
     * @param int|null $stepsLimit
     * @return int
     * @throws Throwable
     */
    protected function processRecord(
        Manifest $manifest,
        int $batch,
        int $stepsLimit = null
    ): int {
        $name = $manifest->file()->tag ?
            "[{$manifest->file()->tag}]{$manifest->file()->table}" :
            $manifest->file()->table;

        $startTime = microtime(true);

        $lastStep = $this
            ->connection()
            ->table(
                app('migro')->table()
            )
            ->where('table', '=', $manifest->file()->table)
            ->when(
                $manifest->file()->tag,
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
            $step->runHook($step::BEFORE_UP);

            $this->upStep($manifest, $step);

            $query = $this->connection()->table(
                app('migro')->table()
            );

            $query->insert([
                'table' => $manifest->file()->table,
                'tag' => $manifest->file()->tag ?: null,
                'step' => $step->number,
                'batch' => $batch,
            ]);

            $step->runHook($step::AFTER_UP);
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
     * @param Manifest $manifest
     * @param Step $step
     * @throws Throwable
     */
    protected function upStep(Manifest $manifest, Step $step): void
    {
        $this->getOutput()->writeln("Step: {$step->number}");

        $callback = function () use ($manifest, $step) {
            $table = $manifest->file()->table;

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

    /**
     * @param array|null $table
     * @param string|null $tag
     * @param string|null $batches
     * @return Collection
     */
    private function resolveRecords(?array $table, ?string $tag, ?string $batches): Collection
    {
        $lastBatch = $this->connection()->table(
            app('migro')->table()
        )->max('batch');

        if (! $lastBatch) {
            return collect();
        }

        $query = $this->connection()->table(
            app('migro')->table()
        );

        if ($table) {
            $query->where('table', '=', $table);
        }

        if ($tag) {
            $query->where('tag', '=', $tag);
        }

        if ($batches) {
            $query->where('batch', '>', $lastBatch - $batches);
        } else {
            $query->where('batch', '=', $lastBatch);
        }

        return $query->latest('ran_at')->get();
    }
}
