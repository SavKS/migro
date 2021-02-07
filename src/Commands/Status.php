<?php

namespace Savks\Migro\Commands;

use Illuminate\Support\Collection;
use Savks\Migro\Support\File;
use stdClass;
use Throwable;

class Status extends BaseCommand
{
    /**
     * @var string
     */
    protected $signature = 'migro:status {--table= : The database table to use}
                {--tag= : Using tagged tables by tag name}
                {--connection= : The database connection to use}';

    /**
     * @var string
     */
    protected $description = 'Show the status of each migration';

    /**
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $table = $this->option('table');
        $tag = $this->option('tag');

        $filesRepository = app('migro')->collectFiles();

        if ($table) {
            $filesRepository = $filesRepository->forTable($table);
        }

        if ($tag) {
            $filesRepository = $filesRepository->taggedAs($tag);
        }

        if ($filesRepository->isEmpty()) {
            $this->warn('Nothing to show...');

            return self::SUCCESS;
        }

        $records = $this->resolveRecords($table, $tag);

        $rows = [];

        $filesCount = count(
            $filesRepository->all()
        );

        [
            'simple' => $simpleFiles,
            'tagged' => $taggedFiles,
        ] = collect($filesRepository->all())
            ->groupBy(function (File $file) {
                return $file->tag ? 'tagged' : 'simple';
            })
            ->transform(function (Collection $files) {
                return $files->sortBy(function (File $file) {
                    return $this->resolveFilename($file->table, $file->tag);
                });
            });

        $files = collect()->merge($simpleFiles)->merge($taggedFiles);

        foreach ($files as $index => $file) {
            $col1 = $this->resolveFilename($file->table, $file->tag);

            $col2 = [];
            $col3 = [];

            foreach ($file->makeManifest()->steps() as $step) {
                $col2[] = $step->number;
                $col3[] = $records->has("{$file->table}{$file->tag}{$step->number}") ?
                    $this->consoler()->format('MIGRATED')->green() :
                    $this->consoler()->format('NOT MIGRATED')->white();
            }

            $rows[] = [
                $col1,
                implode("\n", $col2),
                implode("\n", $col3),
            ];

            if ($index < $filesCount) {
                $rows[] = ['', '', ''];
            }
        }

        $this->table([
            'Name',
            'Step',
            'Status'
        ], $rows);

        return self::SUCCESS;
    }

    /**
     * @param string|null $table
     * @param string|null $tag
     * @return Collection
     */
    protected function resolveRecords(?string $table, ?string $tag): Collection
    {
        $query = $this->connection()->table(
            app('migro')->table()
        );

        if ($table) {
            $query->where('table', '=', $table);
        }

        if ($tag) {
            $query->where('tag', '=', $tag);
        }

        $records = $query->get();

        return $records->keyBy(function (stdClass $record) {
            return "{$record->table}{$record->tag}{$record->step}";
        });
    }
}
