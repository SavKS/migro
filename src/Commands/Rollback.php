<?php

namespace Savks\Migro\Commands;

use Closure;
use DB;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Savks\Consoler\Consoler;
use Savks\Consoler\Support\SpinnerProgress;
use Savks\Migro\Support\File;
use Savks\Migro\Support\Manifest;
use Savks\Migro\Support\Step;
use Schema;
use stdClass;
use Throwable;

class Rollback extends BaseCommand
{
    use ConfirmableTrait;

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
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $table = $this->argument('table');

        $tag = $this->option('tag');
        $stepsLimit = $this->option('steps');
        $batches = $this->option('batches');

        $filesRepository = app('migro')->collectFiles();

        $groupedRecords = $this->resolveGroupedRecords($table, $tag, $batches, $stepsLimit);

        if ($groupedRecords->isEmpty()) {
            $this->warn('Nothing to rollback...');

            return self::SUCCESS;
        }

        foreach ($groupedRecords as $group => $records) {
            $migrationFiles = $filesRepository->forTable(
                $records->first()->table
            );

            if ($tag) {
                $migrationFiles = $migrationFiles->taggedAs($tag);
            } else {
                $migrationFiles = $migrationFiles->withoutTags();
            }

            /** @var File $migrationFile */
            $migrationFile = Arr::first(
                $migrationFiles->all()
            );

            if (! $migrationFile) {
                $this->getOutput()->writeln(
                    sprintf(
                        "<fg=red>Migration not found:</> %s",
                        $this->resolveFilename(
                            $records->first()->table,
                            $records->first()->tag
                        )
                    )
                );

                continue;
            }

            $this->processRecordsGroup(
                $migrationFile->makeManifest(),
                $group,
                $records
            );
        }

        return self::SUCCESS;
    }

    /**
     * @param Manifest $manifest
     * @param string $group
     * @param Collection $records
     * @return void
     * @throws Throwable
     */
    protected function processRecordsGroup(
        Manifest $manifest,
        string $group,
        Collection $records
    ): void {
        $this->getOutput()->writeln("<comment>Rolling back:</comment> {$group}");

        /** @var SpinnerProgress[] $bars */
        $bars = [];

        $minInvolvedStepNumber = $records->min('step');
        $maxInvolvedStepNumber = $records->max('step');

        foreach ($manifest->steps() as $step) {
            if ($step->number < $minInvolvedStepNumber) {
                $this->resolveConsoler()->createSpinnerProgress()->withMessage(
                    "Step {$step->number}: <fg=white>outside the limits</>"
                );
            } elseif ($step->number > $maxInvolvedStepNumber) {
                $this->resolveConsoler()->createSpinnerProgress()->withMessage(
                    "Step {$step->number}: <fg=magenta>rolled back</>"
                );
            } else {
                $bars[$step->number] = $this->resolveConsoler()->createSpinnerProgress()->withMessage(
                    "Step {$step->number}: <fg=yellow>waiting</>"
                );
            }
        }

        $runTime = $this->calcExecutionTime(function () use ($bars, $group, $manifest, $records) {
            foreach ($records as $record) {
                $runTime = $this->calcExecutionTime(function () use ($group, $bars, $record, $manifest) {
                    $step = Arr::first(
                        $manifest->steps(),
                        function (Step $step) use ($record) {
                            return $step->number === $record->step;
                        }
                    );

                    if (! $step) {
                        $bars[$step->number]->withMessage("Step {$record->step}: <fg=red>Not found</>")->fail();

                        $this->getOutput()->writeln("<fg=red>Rolling back failed:</> {$group}");

                        return false;
                    }

                    $bars[$step->number]->withMessage("Step {$record->step}: <fg=cyan>processing</>");

                    try {
                        $step->runHook($step::BEFORE_DOWN);

                        $this->downStep($manifest, $step);

                        $query = $this->connection()->table(
                            app('migro')->table()
                        );

                        $query->where('id', '=', $record->id)->delete();

                        $step->runHook($step::AFTER_DOWN);
                    } catch (\Throwable $e) {
                        $bars[$step->number]
                            ->withMessage("Step {$record->step}: <fg=red>failed</>")
                            ->fail();

                        throw $e;
                    }
                });

                $bars[$record->step]
                    ->withMessage("Step {$record->step}: <fg=green>{$runTime}ms</>")
                    ->finish();
            }
        });

        if ($runTime === null) {
            return;
        }

        $this->getOutput()->writeln("<info>Rolled back:</info>  {$group} ({$runTime}ms)");
    }

    /**
     * @param Manifest $manifest
     * @param Step $step
     * @throws Throwable
     */
    protected function downStep(Manifest $manifest, Step $step): void
    {
        $callback = function () use ($manifest, $step) {
            $table = $manifest->file()->table;

            switch ($step->type) {
                case $step::CREATE:
                    Schema::dropIfExists($table);
                    break;
                case $step::MODIFY:
                    Schema::table($table, $step->down);
                    break;
                case $step::RAW:
                    call_user_func($step->down, $manifest, $step);
                    break;

                default:
                    throw new \RuntimeException("Invalid rollback step type \"{$step->type}\"");
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
     * @param string |null $table
     * @param string|null $tag
     * @param string|null $batches
     * @param int|null $stepsLimit
     * @return Collection|Collection[]
     */
    private function resolveGroupedRecords(
        ?string $table,
        ?string $tag,
        ?string $batches,
        int $stepsLimit = null
    ): Collection {
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

        $query->orderBy('step', 'desc');

        $records = $query->get();

        /** @var Collection[] $groupedRecords */
        $groupedRecords = $records->groupBy(function (stdClass $record) {
            return $this->resolveFilename($record->table, $record->tag);
        })->sortKeys();

        $result = [];

        foreach ($groupedRecords as $group => $records) {
            $result[$group] = $records->sortByDesc('step');

            if ($stepsLimit) {
                $result[$group] = $result[$group]->take($stepsLimit);
            }
        }

        return collect($result);
    }
}
