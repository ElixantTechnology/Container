<?php
namespace Elixant\Components\Container;

/**
 * Copyright (c) 2021 Elixant Technology Ltd.
 *
 * PHP Version 7
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package         elixant-technology/container
 * @copyright   (c) 2021 Elixant Technology Ltd.
 * @author          Alexander M. Schmautz <president@elixant-technology.com>
 * @license         http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version         Release: @package_version@
 */
use Closure;
use Elixant\Components\Container\Exception\BindingResolutionException;
use InvalidArgumentException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Class: BoundMethod
 *
 * Representation of a Bound Method.
 *
 * @package         elixant-platform/container
 * @copyright   (c) 2021 Elixant Technology Ltd.
 * @author          Alexander M. Schmautz <president@elixant-technology.com>
 * @license         http://www.opensource.org/licenses/mit-license.html  MIT License
 */
class BoundMethod
{
    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  Container        $container
     * @param  callable|string  $callback
     * @param  array            $parameters
     * @param  string|null      $defaultMethod
     *
     * @return mixed
     */
    public static function call($container, $callback, array $parameters = [], $defaultMethod = null)
    {
        if (is_string($callback) && !$defaultMethod && method_exists($callback, '__invoke'))
        {
            $defaultMethod = '__invoke';
        }

        if (static::isCallableWithAtSign($callback) || $defaultMethod)
        {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        return static::callBoundMethod(
            $container, $callback, function () use ($container, $callback, $parameters) {
            return $callback(...array_values(static::getMethodDependencies($container, $callback, $parameters)));
        });
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     *
     * @return bool
     */
    protected static function isCallableWithAtSign($callback)
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param  Container    $container
     * @param  string       $target
     * @param  array        $parameters
     * @param  string|null  $defaultMethod
     *
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected static function callClass($container, $target, array $parameters = [], $defaultMethod = null)
    {
        $segments = explode('@', $target);

        // We will assume an @ sign is used to delimit the class name from the method
        // name. We will split on this @ sign and then build a callable array that
        // we can pass right back into the "call" method for dependency binding.
        $method = count($segments) === 2
            ? $segments[1] : $defaultMethod;

        if (is_null($method))
        {
            throw new InvalidArgumentException('Method not provided.');
        }

        return static::call(
            $container, [$container->make($segments[0]), $method], $parameters
        );
    }

    /**
     * Call a method that has been bound to the container.
     *
     * @param  Container  $container
     * @param  callable   $callback
     * @param  mixed      $default
     *
     * @return mixed
     */
    protected static function callBoundMethod($container, $callback, $default)
    {
        if (!is_array($callback))
        {
            return Util::unwrapIfClosure($default);
        }

        // Here we need to turn the array callable into a Class@method string we can use to
        // examine the container and see if there are any method bindings for this given
        // method. If there are, we can call this method binding callback immediately.
        $method = static::normalizeMethod($callback);

        if ($container->hasMethodBinding($method))
        {
            return $container->callMethodBinding($method, $callback[0]);
        }

        return Util::unwrapIfClosure($default);
    }

    /**
     * Normalize the given callback into a Class@method string.
     *
     * @param  callable  $callback
     *
     * @return string
     */
    protected static function normalizeMethod($callback)
    {
        $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

        return "{$class}@{$callback[1]}";
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  Container        $container
     * @param  callable|string  $callback
     * @param  array            $parameters
     *
     * @return array
     *
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    protected static function getMethodDependencies($container, $callback, array $parameters = [])
    {
        $dependencies = [];

        foreach (static::getCallReflector($callback)->getParameters() as $parameter)
        {
            static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, array_values($parameters));
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string  $callback
     *
     * @return ReflectionFunctionAbstract
     *
     * @throws ReflectionException
     */
    protected static function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false)
        {
            $callback = explode('::', $callback);
        } else if (is_object($callback) && !$callback instanceof Closure)
        {
            $callback = [$callback, '__invoke'];
        }

        return is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param  Container            $container
     * @param  ReflectionParameter  $parameter
     * @param  array                $parameters
     * @param  array                $dependencies
     *
     * @return void
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    protected static function addDependencyForCallParameter($container,
        $parameter,
        array &$parameters,
        &$dependencies)
    {
        if (array_key_exists($paramName = $parameter->getName(), $parameters))
        {
            $dependencies[] = $parameters[$paramName];

            unset($parameters[$paramName]);
        } else if (!is_null($className = Util::getParameterClassName($parameter)))
        {
            if (array_key_exists($className, $parameters))
            {
                $dependencies[] = $parameters[$className];

                unset($parameters[$className]);
            } else
            {
                $dependencies[] = $container->make($className);
            }
        } else if ($parameter->isDefaultValueAvailable())
        {
            $dependencies[] = $parameter->getDefaultValue();
        } else if (!$parameter->isOptional() && !array_key_exists($paramName, $parameters))
        {
            $message = "Unable to resolve dependency [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}";

            throw new BindingResolutionException($message);
        }
    }
}
