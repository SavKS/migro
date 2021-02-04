<?php

namespace Savks\Migro\Support;

use Closure;

class Step
{
    public const CREATE = 'create';
    public const MODIFY = 'modify';
    public const RAW = 'raw';

    /**
     * @var int
     */
    public $number;

    /**
     * @var Closure
     */
    public $up;

    /**
     * @var Closure|null
     */
    public $down;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $type;

    /**
     * @var bool
     */
    public $withoutTransaction = false;

    /**
     * Step constructor.
     * @param int $number
     * @param Closure $up
     * @param Closure|null $down
     * @param string $type
     */
    public function __construct(int $number, Closure $up, ?Closure $down, string $type)
    {
        $this->number = $number;
        $this->up = $up;
        $this->down = $down;
        $this->type = $type;
    }

    /**
     * @param string $text
     * @return $this
     */
    public function description(string $text): Step
    {
        $this->description = $text;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutTransaction(): Step
    {
        $this->withoutTransaction = true;

        return $this;
    }
}
