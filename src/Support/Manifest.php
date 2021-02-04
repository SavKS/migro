<?php

namespace Savks\Migro\Support;

use Closure;

class Manifest
{
    /**
     * @var string
     */
    protected $table;

    /**
     * @var string|null
     */
    protected $tag;

    /**
     * @var Step[]
     */
    protected $steps = [];

    /**
     * Factory constructor.
     * @param string $table
     * @param string|null $tag
     */
    public function __construct(string $table, string $tag = null)
    {
        $this->table = $table;
        $this->tag = $tag;
    }

    /**
     * @return string
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function tag(): string
    {
        return $this->tag;
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
