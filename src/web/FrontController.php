<?php

namespace Spine\Web;

use Spine\Container;

/**
 *
 */
class FrontController implements ControllerInterface
{

    /**
     * @var Routes
     */
    protected $routes;

    /**
     * @param Container $container
     * @param Routes    $routes
     */
    public function __construct(Container $container, Routes $routes)
    {
        $this->container = $container;
        $this->routes    = $routes;
    }

    /**
     *
     */
    public function dispatch()
    {
        $controllerClassName = $this->routes->resolve();
        /** @var $controller ControllerInterface */
        $controller = $this->container->resolve($controllerClassName);
        $controller->dispatch();
    }

}
