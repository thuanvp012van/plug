<?php

namespace Penguin\Component\Plug;

use BadMethodCallException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

trait Plug
{
    /**
     * The registered plugs.
     *
     * @var array<Closure>
     */
    protected static $plugs = [];

    /**
     * Register a plug.
     *
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public static function plug(string $name, callable $callback): void
    {
        static::$plugs[$name] = $callback;
    }

    /**
     * Mix another object into the class.
     *
     * @param object $mixin
     * @param bool $replace
     * @return void
     *
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public static function mixin(object $mixin, bool $replace = true): void
    {
        $reflectionClass = new ReflectionClass($mixin);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$replace && static::hasplug($method->name)) {
                throw new LogicException("Can't register two functions {$method->name}");
            }
            
            if ($replace || !static::hasplug($method->name)) {
                $returnType = (string)$method->getReturnType();
                if ($returnType === 'Closure' || $returnType === 'callable') {
                    static::plug($method->name, $method->invoke($mixin));
                } else {
                    throw new RuntimeException(sprintf(
                        'The %s::%s must declare return a Closure', $reflectionClass->getName(), $method->name
                    ));
                }
            }
        }
    }

    /**
     * Check if the plug is attached.
     *
     * @param string $name
     * @return bool
     */
    public static function hasPlug(string $name): bool
    {
        return isset(static::$plugs[$name]);
    }

    /**
     * Unplug.
     *
     * @param string $name
     * @return void
     */
    public static function unplug(string $name): void
    {
        unset(static::$plugs[$name]);
    }

    /**
     * Remove plugs.
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$plugs = [];
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters = []): mixed
    {
        if (!static::hasplug($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $plug = static::$plugs[$method];
        $plug = $plug->bindTo(null, static::class);
        
        return $plug(...$parameters);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $parameters = []): mixed
    {
        if (!static::hasplug($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $plug = static::$plugs[$method];
        $plug = $plug->bindTo($this, static::class);

        return $plug(...$parameters);
    }
}