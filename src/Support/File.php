<?php

namespace Savks\Migro\Support;

class File
{
    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $table;

    /**
     * @var string|null
     */
    public $tag;

    /**
     * MigrationFile constructor.
     * @param string $path
     * @param string $table
     * @param string|null $tag
     */
    protected function __construct(string $path, string $table, string $tag = null)
    {
        $this->path = $path;
        $this->table = $table;
        $this->tag = $tag;
    }

    /**
     * @param string $path
     * @return static|null
     */
    public static function tryMakeFromPath(string $path): ?File
    {
        $filename = basename($path, '.php');

        if (preg_match('/^(([\w-]+)\.)?([\w-]+)$/', $filename, $matches) === 0) {
            return null;
        }

        [$table, $tag] = $matches[3] ? [ $matches[3], $matches[2] ] : [ $matches[2], null ];

        return new static($path, $table, $tag ?: null);
    }

    /**
     * @return Manifest
     */
    public function makeManifest(): Manifest
    {
        return new Manifest($this);
    }
}
