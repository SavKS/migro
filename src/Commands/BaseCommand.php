<?php

namespace Savks\Migro\Commands;

use Closure;
use DB;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Savks\Consoler\Consoler;

abstract class BaseCommand extends Command
{
    /**
     * @var Consoler
     */
    protected $consoler;

    /**
     * @param string|null $table
     * @param string|null $tag
     * @return string|null
     */
    protected function resolveFilename(?string $table, ?string $tag): ?string
    {
        return $tag ? "{$tag}.{$table}" : $table;
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
     * @return Consoler
     */
    protected function consoler(): Consoler
    {
        if ($this->consoler === null) {
            $this->consoler = Consoler::fromOutputStyle(
                $this->getOutput()
            );
        }

        return $this->consoler;
    }
}
