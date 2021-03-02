<?php
namespace Elixant\Components\Container\Traits;

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
 * @copyright       2021 (c) Elixant Technology Ltd.
 * @author          Alexander M. Schmautz <president@elixant-technology.com>
 * @license         http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version         Release: @package_version@
 */
use Elixant\Components\Container\Container;

/**
 * Class: ContainerAwareTrait
 *
 * The Container Aware Trait. When implemented, simply type-hint
 * the Container instance from the constructor and assign it to
 * the container parameter, to gain immediate access to the Container.
 *
 * @package     elixant-platform/container
 * @copyright   2021 (c) Elixant Technology Ltd.
 * @author      Alexander M. Schmautz <president@elixant-technology.com>
 * @license     http://www.opensource.org/licenses/mit-license.html  MIT License
 */
trait ContainerAwareTrait
{
    /**
     * The IoC Container Instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Retrieves the Container Instance
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set/Bind the IoC Container Instance to the local class for
     * ease of access.
     *
     * @param  Container  $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Retrieve the IoC Container Instance, binding it to the local class
     * if it hasn't already been.
     *
     * @return Container
     */
    protected function container(): Container
    {
        if (null === $this->container || !$this->container instanceof Container)
        {
            $this->setContainer(Container::getInstance());
        }

        return $this->container;
    }
}