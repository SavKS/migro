<?php

namespace Savks\Migro\Support;

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
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
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
