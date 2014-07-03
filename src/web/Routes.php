<?php

namespace Spine\Web;

use ReflectionObject;

/**
 * Extend this class, and add your routes
 */
class Routes
{

    /**
     * Add your Routes here
     *
     * @var array
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
     * Name of the class to dispatch the request
     *
     * @return string
     */
    public function resolve()
    {
        $pathParams = array();
        $path       = $this->request->path();

        foreach ($this->routes as $pattern => $controllerClassName) {

            $regEx = self::compileString($pattern);

            if (preg_match($regEx, $path, $pathParams)) {
                // leave only the values with string keys
                $stringKeys = array_filter(array_keys($pathParams), 'is_string');
                $this->request->setPathParams(array_intersect_key($pathParams, array_flip($stringKeys)));

                $routesReflectionObject = new ReflectionObject($this);
                $controllerClassName    = $routesReflectionObject->getNamespaceName() . "\\" . $controllerClassName;

                return $controllerClassName;
            }
        }

        return $this->fallback($path);
    }

    /**
     * Extend if needed
     *
     * @param string $path
     *
     * @throws HttpNotFoundException
     * @return string ClassName
     */
    protected function fallback($path)
    {
        throw new HttpNotFoundException(get_class($this) . " - Route '$path' not found.");
    }

    /**
     * @var Request
     */
    private $request;

    /**
     * The regular expression for a wildcard.
     *
     * @var string
     */
    private static $wildcard = '(?P<$1>([a-zA-Z0-9\.\,\-_%=]+))';

    /**
     * The regular expression for an optional wildcard.
     *
     * @var string
     */
    private static $optional = '(?:/(?P<$1>([a-zA-Z0-9\.\,\-_%=]+))';

    /**
     * The regular expression for a leading optional wildcard.
     *
     * @var string
     */
    private static $leadingOptional = '(\/$|^(?:(?P<$2>([a-zA-Z0-9\.\,\-_%=]+)))';

    /**
     * Compile the given route string as a regular expression.
     *
     * @param  string $value
     *
     * @return string
     */
    private static function compileString($value)
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

}

