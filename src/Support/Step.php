<?php

namespace Savks\Migro\Support;

use Closure;

class Step
{
    public const CREATE = 'create';
    public const MODIFY = 'modify';
    public const RAW = 'raw';

    public const BEFORE_UP = 'before_up';
    public const AFTER_UP = 'after_up';
    public const BEFORE_DOWN = 'before_down';
    public const AFTER_DOWN = 'after_down';

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
     * @var array
     */
    protected $hooks = [
        self::BEFORE_UP => [],
        self::AFTER_UP => [],
        self::BEFORE_DOWN => [],
        self::AFTER_DOWN => [],
    ];

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

    /**
     * @param string $name
     * @param Closure $handler
     * @return $this
     */
    protected function addHook(string $name, Closure $handler): Step
    {
        $this->hooks[$name][] = $handler;

        return $this;
    }

    /**
     * @param Closure $handler
     * @return $this|Step
     */
    public function beforeUp(Closure $handler): Step
    {
        return $this->addHook(self::BEFORE_UP, $handler);
    }

    /**
     * @param Closure $handler
     * @return $this|Step
     */
    public function afterUp(Closure $handler): Step
    {
        return $this->addHook(self::AFTER_UP, $handler);
    }

    /**
     * @param Closure $handler
     * @return $this|Step
     */
    public function beforeDown(Closure $handler): Step
    {
        return $this->addHook(self::BEFORE_DOWN, $handler);
    }

    /**
     * @param Closure $handler
     * @return $this|Step
     */
    public function afterDown(Closure $handler): Step
    {
        return $this->addHook(self::AFTER_DOWN, $handler);
    }

    /**
     * @param string $name
     */
    public function runHook(string $name): void
    {
        foreach ($this->hooks[$name] as $handler) {
            $handler();
        }
    }
}
