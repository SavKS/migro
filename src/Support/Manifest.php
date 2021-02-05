<?php

namespace Savks\Migro\Support;

use Closure;

class Manifest
{
    /**
     * @var Step[]
     */
    protected $steps = [];

    /**
     * @var File
     */
    protected $file;

    /**
     * Factory constructor.
     * @param File $migrationFile
     */
    public function __construct(File $migrationFile)
    {
        $this->file = $migrationFile;

        $this->collectSteps();
    }

    /**
     * @return void
     */
    protected function collectSteps(): void
    {
        require $this->file->path;
    }

    /**
     * @return File
     */
    public function file(): File
    {
        return $this->file;
    }

    /**
     * @return Step[]
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @param Closure $up
     * @return Step
     */
    public function create(Closure $up): Step
    {
        return $this->addStep($up, null, Step::CREATE);
    }

    /**
     * @param Closure $up
     * @param Closure $down
     * @return Step
     */
    public function modify(Closure $up, Closure $down): Step
    {
        return $this->addStep($up, $down, Step::MODIFY);
    }

    /**
     * @param Closure $up
     * @param Closure $down
     * @return Step
     */
    public function raw(Closure $up, Closure $down): Step
    {
        return $this->addStep($up, $down, Step::RAW);
    }

    /**
     * @param Closure $up
     * @param Closure|null $down
     * @param string $type
     * @return Step
     */
    protected function addStep(Closure $up, ?Closure $down, string $type): Step
    {
        $number = count($this->steps) + 1;

        $step = new Step($number, $up, $down, $type);

        $this->steps[] = $step;

        return $step;
    }
}
