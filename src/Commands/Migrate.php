<?php

namespace Savks\Migro\Commands;

use Illuminate\Console\ConfirmableTrait;
use Savks\Migro\Support\{
    Manifest,
    Step
};
use RuntimeException;
use Savks\Consoler\Support\SpinnerProgress;
use Savks\LaravelUtils\Utils;
use Schema;
use Throwable;

class Migrate extends BaseCommand
{
    use ConfirmableTrait;

    /**
     * @var string
     */
    protected $signature = 'migro:migrate {--table= : The database table to use}
                {--tag= : Using tagged tables by tag name}
                {--steps= : Limitation on the number of steps per migration}
                {--connection= : The database connection to use}';

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

        $table = $this->option('table');
        $tag = $this->option('tag');
        $stepsLimit = $this->option('steps');

        $filesRepository = app('migro')->collectFiles();

        if ($table) {
            $filesRepository = $filesRepository->forTable($table);
        }

        if ($tag) {
            $filesRepository = $filesRepository->taggedAs($tag);
        } else {
            $filesRepository = $filesRepository->withoutTags();
        }

        if ($filesRepository->isEmpty()) {
            $this->warn('Nothing to migrate...');

            return self::SUCCESS;
        }

        $migrationsTable = app('migro')->table();

        $lastBatch = $this->connection()->table($migrationsTable)->max('batch');

        $currentBatch = $lastBatch ? $lastBatch + 1 : 1;

        $stepsCount = 0;

        foreach ($filesRepository->all() as $file) {
            $stepsCount += $this->processMigrationManifest(
                $file->makeManifest(),
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
    protected function processMigrationManifest(
        Manifest $manifest,
        int $batch,
        int $stepsLimit = null
    ): int {
        $name = $this->resolveFilename(
            $manifest->file()->table,
            $manifest->file()->tag
        );

        $totalMeasuring = Utils::startMeasuring();

        $lastStep = $this->resolveLastStep($manifest);

        $steps = array_slice(
            $manifest->steps(),
            $lastStep,
            $stepsLimit
        );

        $stepsCount = count($steps);

        if (! $stepsCount) {
            return 0;
        }

        $totalStepsCount = count(
            $manifest->steps()
        );

        $consoler = $this->consoler();

        $consoler->writelnToOutput("<comment>Migrating:</comment> {$name}");

        /** @var SpinnerProgress[] $bars */
        $bars = [];

        for ($i = 0; $i < $totalStepsCount; $i++) {
            if ($i < $lastStep) {
                $consoler->createSpinnerProgress()->withMessage(
                    'Step ' . ($i + 1) . ": {$consoler->format('migrated')->cyan()}"
                )->finish();
            } elseif ($i < $lastStep + $stepsCount) {
                $bars[$i + 1] = $consoler->createSpinnerProgress()->withMessage(
                    'Step ' . ($i + 1) . ": {$consoler->format('waiting')->yellow()}"
                );
            } else {
                $consoler->createSpinnerProgress()->withMessage(
                    'Step ' . ($i + 1) . ': <fg=white>outside the limits</>'
                );
            }
        }

        foreach ($steps as $index => $step) {
            $bar = $bars[$step->number]->withMessage(
                "Step {$step->number}: {$consoler->format('processing')->blue()->blink()}"
            );

            try {
                $stepMeasuring = Utils::startMeasuring();

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

                $stepMeasuring->stop();

                $bar->withMessage(
                    sprintf(
                        'Step %s: %s',
                        $step->number,
                        $consoler->format("{$stepMeasuring->executionTime(false)}ms")->green()
                    )
                )->success();
            } catch (Throwable $e) {
                $bar->withMessage(
                    "Step {$step->number}: {$consoler->format('failed')->red()}"
                )->fail();

                throw $e;
            }
        }

        $totalMeasuring->stop();

        $this->consoler()->writelnToOutput("<info>Migrated:</info>  {$name} ({$totalMeasuring->executionTime(false)}ms)");

        return $stepsCount;
    }

    /**
     * @param Manifest $manifest
     * @param Step $step
     * @throws Throwable
     */
    protected function upStep(Manifest $manifest, Step $step): void
    {
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
                    throw new RuntimeException("Invalid migration step type \"{$step->type}\"");
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
     * @param Manifest $manifest
     * @return int
     */
    protected function resolveLastStep(Manifest $manifest): int
    {
        $query = $this->connection()->table(
            app('migro')->table()
        );

        $query->where('table', '=', $manifest->file()->table);

        if ($manifest->file()->tag) {
            $query->where('tag', '=', $manifest->file()->tag);
        }

        return $query->max('step') ?? 0;
    }
}
