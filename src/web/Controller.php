<?php

namespace Spine\Web;

use ReflectionMethod;
use Spine\Container;

/**
 * Basic Http Controller
 *
 * @since 2011-10-03
 */
abstract class Controller extends BaseController implements ControllerInterface
{

    /**
     * @var Container
     */
    private $container;

    /**
     * @param Container $container
     */
    public function injectContainer(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return void
     */
    public function dispatch()
    {
        if (!isset($this->request)) {
            throw new \RuntimeException("\$this->request is not set. Check if this controller's (" . get_class($this) . ") constructor was overridden.");
        }

        $methodName = strtolower($this->request->type());

        $this->callMethod($methodName);
    }


    /**
     * @param string $methodName
     *
     * @throws HttpMethodNotAllowedException
     */
    protected function callMethod($methodName)
    {
        if (!method_exists($this, $methodName)) {
            $class = get_class($this);
            throw new HttpMethodNotAllowedException(sprintf("'%s->%s()' not implemented", $class, $methodName));
        }

        $this->container->callMethod($this, $methodName);
    }

}