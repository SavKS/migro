<?php

namespace Savks\Migro\Commands;

use DB;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;

class Install extends Command
{
    /**
     * @var string
     */
    protected $signature = 'migro:install {--connection=}';

    /**
     * @var string
     */
    protected $description = 'Create the migration table';

    /**
     * @return int
     */
    public function handle(): int
    {
        $table = app('migro')->table();

        $connection = DB::connection(
            $this->option('connection')
        );

        if ($connection->getSchemaBuilder()->hasTable($table)) {
            $this->warn('Migration table already exists.');

            return self::FAILURE;
        }

        $connection->getSchemaBuilder()->create($table, function (Blueprint $table) {
            $table->increments('id');

            $table->string('table');

            $table->string('tag')->nullable();

            $table->integer('step');

            $table->integer('batch');

            $table->timestamp('ran_at')->default(
                DB::raw('CURRENT_TIMESTAMP')
            );
        });

        $this->info('Migration table created successfully.');

        return self::SUCCESS;
    }
}
