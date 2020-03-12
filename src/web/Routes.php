<?php

namespace Spine\Web;

use Closure;
use ReflectionObject;

/**
 * Extend this class, and add your routes
 */
abstract class Routes
{

    /**
     * Add your Routes here
     *
     * @var array
     * @deprecated Use routes();
     */
    protected $routes = array(); // <-- extend and add your routes

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Extend and add your routes
     *
     * @return array
     */
    protected function routes()
    {
        return array();

    }

    /**
     * @var Request
     */
    protected $request;

    /**
     * Name of the class to dispatch the request
     *
     * @throws HttpNotFoundException
     * @return string
     */
    public function resolve()
    {

        $path = $this->request->path();

        $routes = array_merge($this->routes());

        $controllerName = $this->matchRoute($routes, $path);
        if ($controllerName) {

            return $controllerName;
        }

        return $this->notFound($path);
    }

    /**
     * @param $routes
     * @param $path
     *
     * @return null|string
     * @throws HttpForbiddenException
     */
    public function matchRoute($routes, $path)
    {
        $pathParams = array();

        foreach ($routes as $pattern => $route) {

            $regEx = self::compileString($pattern);

            if (preg_match($regEx, $path, $pathParams)) {
                if (is_string($route)) {
                    $temp                       = $route;
                    $route                      = new Route($this->request);
                    $route->controllerClassName = $temp;
                }

                if (is_object($route) && ($route instanceof Closure)) {
                    $route = $route();
                }

                if (!$route->isAllowed()) {
                    throw new HttpForbiddenException("Route not allowed.");
                }

                // leave only the values with string keys
                $stringKeys = array_filter(array_keys($pathParams), 'is_string');
                $this->request->setPathParams(array_intersect_key($pathParams, array_flip($stringKeys)));

                //Old code for casses where controller in routes was a string:
                //$controllerClassName = $this->getNameSpace() . "\\" . $route->controllerClassName;

                //New way where controller is in routes as controllerName::class:
                $controllerClassName = $route->controllerClassName;

                return $controllerClassName;
            }
        }
        return null;
    }

    /**
     * Extend if needed
     *
     * @param string $path
     *
     * @throws HttpNotFoundException
     * @return string ClassName
     */
    protected function notFound($path)
    {
        throw new HttpNotFoundException(get_class($this) . " - Route '$path' not found.");
    }

    /**
     * The regular expression for a wildcard.
     *
     * @var string
     */
    private static $wildcard = '(?P<$1>([a-zA-Z0-9\.\,\-_%=:\(\)]+))';

    /**
     * The regular expression for an optional wildcard.
     *
     * @var string
     */
    private static $optional = '(?:/(?P<$1>([a-zA-Z0-9\.\,\-_%=:\(\)]+))';

    /**
     * The regular expression for a leading optional wildcard.
     *
     * @var string
     */
    private static $leadingOptional = '(\/$|^(?:(?P<$2>([a-zA-Z0-9\.\,\-_%=:\(\)]+)))';

    /**
     * Compile the given route string as a regular expression.
     *
     * @param  string $value
     *
     * @return string
     */
    public static function compileString($value)
    {
        $value = static::compileOptional(static::compileParameters($value));

        return trim($value) == '' ? null : '#^' . $value . '$#u';

    }

    /**
     * Compile the wildcards for a given string.
     *
     * @param  string $value
     *
     * @return string
     */
    private static function compileParameters($value)
    {
        return preg_replace('/\{((.*?)[^?])\}/', self::$wildcard, $value);
    }

    /**
     * Compile the standard optional wildcards for a given string.
     *
     * @param  string $value
     * @param  int    $custom
     *
     * @return string
     */
    private static function compileOptional($value, $custom = 0)
    {
        $value = preg_replace('/\/\{(.*?)\?\}/', self::$optional, $value, -1, $count);

        $value = preg_replace('/^(\{(.*?)\?\})/', self::$leadingOptional, $value, -1, $leading);

        $total = $leading + $count + $custom;

        if ($total > 0) {
            $value .= str_repeat(')?', $total);
        }

        return $value;

    }

    private function getNameSpace()
    {
        $routesReflectionObject = new ReflectionObject($this);
        return $routesReflectionObject->getNamespaceName();
    }

}

