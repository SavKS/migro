<?php

return [
    /**
     * Migrations table
     */
    'table' => 'migro',

    /**
     * Paths for automatic register migrations. Supports: dir path or glob rule.
     */
    'paths' => [
        database_path('migro')
    ],
];
