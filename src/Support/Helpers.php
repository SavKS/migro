<?php

namespace Savks\Migro\Support;

use Illuminate\Support\Str;

class Helpers
{
    /**
     * @param string $value
     * @return bool
     */
    public static function isValidTag(string $value): bool
    {
        return preg_match('/^[\w-]+$/', $value) > 0;
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function isMigrationName(string $value): bool
    {
        return preg_match('/^([\w-]+\.)?[\w-]+\.php$/', $value) > 0;
    }

    /**
     * @param string $path
     * @return array|null
     */
    public static function parseMigrationPath(string $path): ?array
    {
        $filename = basename($path, '.php');

        if (preg_match('/^([\w-]+\.)?([\w-]+)$/', $filename, $matches) === 0) {
            return null;
        }

        return $matches[2] ? [ $matches[2], $matches[1] ] : [ $matches[1], null ];
    }

    /**
     * @param array $paths
     * @param string|null $table
     * @param string|null $tag
     * @return array
     */
    public static function filterMigrationFilePaths(array $paths, string $table = null, string $tag = null): array
    {
        $result = [];

        foreach ($paths as $path) {
            $filename = basename($path, '.php');

            if ($tag && $table) {
                if ($filename === "{$tag}.{$table}") {
                    $result[] = $path;
                }

                continue;
            }

            if ($tag) {
                if (Str::startsWith($filename, "{$tag}.")) {
                    $result[] = $path;
                }

                continue;
            }

            if ($table) {
                if ($filename === $table) {
                    $result[] = $path;
                }

                continue;
            }

            $result[] = $path;
        }

        return $result;
    }
}
