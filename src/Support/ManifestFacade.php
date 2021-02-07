<?php

namespace Savks\Migro\Support;

use Closure;
use ReflectionException;
use ReflectionFunction;

class ManifestFacade
{
    /**
     * @param Closure $closure
     * @return Manifest
     * @throws ReflectionException
     */
    protected static function resolveClosureThis(Closure $closure): Manifest
    {
        $reflection = new ReflectionFunction($closure);

        return $reflection->getClosureThis();
    }

    /**
     * @param Closure $up
     * @return Step
     */
    public static function create(Closure $up): Step
    {
        return static::resolveClosureThis($up)->create($up);
    }

    /**
     * @param Closure $up
     * @param Closure $down
     * @return Step
     */
    public static function modify(Closure $up, Closure $down): Step
    {
        return static::resolveClosureThis($up)->modify($up, $down);
    }

    /**
     * @param Closure $up
     * @param Closure $down
     * @return Step
     */
    public static function raw(Closure $up, Closure $down): Step
    {
        return static::resolveClosureThis($up)->raw($up, $down);
    }
}
