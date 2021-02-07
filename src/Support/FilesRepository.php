<?php

namespace Savks\Migro\Support;

use Closure;
use Illuminate\Support\Arr;

class FilesRepository
{
    /**
     * @var File[]
     */
    protected $items = [];

    /**
     * MigrationFilesRepository constructor.
     * @param array $items
     */
    protected function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @return File[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @param Closure|null $filter
     * @return File|null
     */
    public function first(Closure $filter = null): ?File
    {
        return Arr::first($this->items, $filter);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * @param string $tag
     * @return $this
     */
    public function taggedAs(string $tag): FilesRepository
    {
        $result = [];

        foreach ($this->items as $item) {
            if ($item->tag === $tag) {
                $result[] = $item;
            }
        }

        return new static($result);
    }

    /**
     * @param string|null $table
     * @param string|null $tag
     * @return File|FilesRepository
     */
    public function pickUp(?string $table, ?string $tag)
    {
        $items = $this;

        if ($table) {
            $items = $items->forTable($table);
        }

        if ($tag) {
            $items = $items->taggedAs($tag);
        } else {
            $items = $items->withoutTags();
        }

        return $table && $tag ?
            Arr::first(
                $items->all()
            ) :
            $items;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function forTable(string $table): FilesRepository
    {
        $result = [];

        foreach ($this->items as $item) {
            if ($item->table === $table) {
                $result[] = $item;
            }
        }

        return new static($result);
    }

    /**
     * @return $this
     */
    public function withoutTags(): FilesRepository
    {
        $result = [];

        foreach ($this->items as $item) {
            if ($item->tag === null) {
                $result[] = $item;
            }
        }

        return new static($result);
    }

    /**
     * @param array $filePaths
     * @return FilesRepository|File[]
     */
    public static function from(array $filePaths): FilesRepository
    {
        $items = [];

        foreach ($filePaths as $filePath) {
            if ($migrationFile = File::tryMakeFromPath($filePath)) {
                $items[] = $migrationFile;
            }
        }

        return new static($items);
    }
}
