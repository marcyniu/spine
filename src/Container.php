<?php

namespace Spine;

use ReflectionClass;
use ReflectionException;
use ReflectionObject;

/**
 * @since  2014-01-20
 * @author Lance Rushing
 */
class Container
{
    /**
     * @var array
     */
    protected $objects = array();
    private $typeFactories = array();

    protected $registerParentsClassNames = true;

    /**
     * @var array
     */
    private $resolvingClasses = [];

    /**
     *
     */
    public function __construct()
    {
        // register itself. Note: be careful with depending on the container, only factory like classes should ever need it....
        $this->register($this);
    }

    /**
     * Registers and Object -- fluent
     *
     * @param $object
     *
     * @return Container
     * @return \Spine\Container
     */
    public function register($object)
    {

        $reflectionObject = new ReflectionObject($object);

        $this->registerType($reflectionObject->getName(), $object);

        // register parent classes also
        // @todo this might not work in the long run...

        // Testing the idea of not registering the parent class names as provided for by this object
        if ($this->registerParentsClassNames) {
            $this->registerParents($object, $reflectionObject);
            $this->registerInterfaces($object, $reflectionObject);

        }

        return $this;
    }

    /**
     * @param                 $object
     * @param ReflectionClass $reflectionClass
     */
    private function registerParents($object, ReflectionClass $reflectionClass)
    {
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->registerType($parentClass->getName(), $object);
            $this->registerParents($object, $parentClass);

        }
    }

    /**
     * @param                 $object
     * @param ReflectionClass $reflectionClass
     */
    private function registerInterfaces($object, ReflectionClass $reflectionClass)
    {
        $interfaceNames = $reflectionClass->getInterfaceNames();
        foreach ($interfaceNames as $interfaceName) {
            $this->registerType($interfaceName, $object);
        }
    }

    /**
     * @param string $type Name of class/interface the object extends/implements
     * @param object $object
     */
    public function registerType($type, $object)
    {
        $this->objects[$type] = $object;
    }

    /**
     * @param string   $type
     * @param callable $callableFactory
     * @param bool     $registerParents Will also register the factory for the parent class of $type
     */
    public function registerTypeFactory($type, $callableFactory, $registerParents = true)
    {

        $type = trim($type, "\\"); // trim off any namespace delimiters

        if ($registerParents) {
            $reflectionClass = new ReflectionClass($type);

            $this->typeFactories[$reflectionClass->getName()] = $callableFactory;

            // recurs into parents classes
            $parentClass = $reflectionClass->getParentClass();
            if ($parentClass) {
                $this->registerTypeFactory($parentClass->getName(), $callableFactory);
            }
        } else {
            $this->typeFactories[$type] = $callableFactory;
        }

    }

    /**
     * @param string $className
     *
     * @throws ContainerException
     * @return mixed
     */
    public function resolve($className)
    {

        $className = trim($className, "\\");

        if (!class_exists($className) && !interface_exists($className)) {
            throw new ContainerException("Class/Interface '$className' does not exist");
        }



        $key = $className; // @note, this used to make lower case here, b/c auto loader might be case sensitive, but maybe not anymore
        if (!isset($this->objects[$key])) {
            $instance = $this->make($className);
        }


        return $this->objects[$key];

    }

    /**
     * Instantiates the given className
     *
     * @param $className
     *
     * @throws ContainerException
     * @return object
     */
    public function make($className)
    {
        $this->resolvingClasses[$className] = 1;

        $reflectionClass = new ReflectionClass($className);
        $key             = $reflectionClass->name;

        // Confirm it can be created.
        if (!$reflectionClass->isInstantiable() && !isset($this->typeFactories[$key])) {
            throw new ContainerException("The type " . $reflectionClass->name . " is not instantiable");
        }

        if (isset($this->typeFactories[$key])) {
            $instance = $this->callFactory($this->typeFactories[$key]);
        } else {
            $instance = $this->createInstance($reflectionClass);
        }

        unset($this->resolvingClasses[$className]);

        $this->register($instance);
        $this->invokeInjectMethods($reflectionClass, $instance);

        return $instance;

    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    public function getSignature(\ReflectionFunctionAbstract $reflectionMethod)
    {
        $params = $reflectionMethod->getParameters();

        $signature = array();
        /** @var $reflectionParameter \ReflectionParameter */
        foreach ($params as $reflectionParameter) {

            try {
                $class = $reflectionParameter->getClass();
            } catch (ReflectionException $e) {
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf(
                    "Method '%s:%s()' has unknown type hint for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name,
                    $reflectionParameter->name,
                    $reflectionMethod->getFileName(),
                    $reflectionMethod->getEndLine()
                );
                throw new ContainerException($msg, 999, $e);
            }

            if (is_null($class)) { // no class for argument
                if ($reflectionParameter->isOptional()) { // has default value
                    continue; // skip adding this parameter.. will break for mixed signatures.
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf(
                    "Method '%s:%s()' has no type hint  and no default value for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name,
                    $reflectionParameter->name,
                    $reflectionMethod->getFileName(),
                    $reflectionMethod->getEndLine()
                );
                throw new ContainerException($msg);

            }

            $signature[$reflectionParameter->getName()] = $class instanceof ReflectionClass ? ltrim(
                $class->getName(),
                '\\'
            ) : null;
        }
        return $signature;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    public function resolveArguments(\ReflectionFunctionAbstract $reflectionMethod)
    {

        $signature = $this->getSignature($reflectionMethod);
        $args      = array();
        foreach ($signature as $name => $type) {

            if (isset($this->resolvingClasses[$type])) {

                if ($reflectionMethod instanceof \ReflectionFunction ) {
                    /** @var \ReflectionFunction $reflectionMethod */
                    $msg = sprintf('Circular Reference Detected. Function defined in "%s:%s" requires type "%s".', $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type );
                } else {
                    /** @var \ReflectionMethod $reflectionMethod */
                    $msg = sprintf('Circular Reference Detected. Method "%s::%s" defined in "%s:%s requires type "%s".', $reflectionMethod->class,$reflectionMethod->getName(), $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type);
                }

                throw new ContainerException($msg);
            }
            $args[$name] = $this->resolve($type);
        }
        return $args;

    }

    /**
     * Will invoke any methods, who's name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     * @param                 $instance
     */
    private function invokeInjectMethods(ReflectionClass $reflectionClass, $instance)
    {

        // find methods matching /^inject/
        $injectMethods = (array_filter(
            $reflectionClass->getMethods(),
            function (\ReflectionMethod $reflectionMethod) {
                return (preg_match("/^inject/", $reflectionMethod->getName()));
            }
        ));

        // Invoke the methods
        /** @var $injectMethod \ReflectionMethod */
        foreach ($injectMethods as $injectMethod) {
            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }
    }

    /**
     * Will create an instance of the given class.
     * Note: All constructor arguments must have 'resolvable' type hinting
     *
     * @param $reflectionClass
     *
     * @return mixed
     */
    private function createInstance(ReflectionClass $reflectionClass)
    {
        $constructorReflectMethod = $reflectionClass->getConstructor();

        if ($constructorReflectMethod === null) { // no constructor.
            $instance = $reflectionClass->newInstance();
        } else {
            $args     = $this->resolveArguments($constructorReflectMethod);
            $instance = $reflectionClass->newInstanceArgs($args);
        }
        return $instance;
    }

    public function callFunction($callable)
    {

        $reflectionFunction = new \ReflectionFunction($callable);

        $args = $this->resolveArguments($reflectionFunction);

        return $reflectionFunction->invokeArgs($args);
    }

    /**
     * @param mixed  $class Either a string containing the name of the class to reflect, or an object.
     * @param string $methodName
     *
     * @return mixed
     */
    public function callMethod($class, $methodName)
    {
        $reflectionClass  = new ReflectionClass($class);
        $reflectionMethod = $reflectionClass->getMethod($methodName);
        $args             = $this->resolveArguments($reflectionMethod);
        return $reflectionMethod->invokeArgs($class, $args);
    }

    /**
     * @param $callable
     *
     * @return object
     * @throws ContainerException
     */
    private function callFactory($callable)
    {

        $instance = $this->callFunction($callable);

        if (!is_object($instance)) {
            $reflectionFunction = new \ReflectionFunction($callable);
            $error              = sprintf(
                "%s (%u:%u)",
                $reflectionFunction->getFileName()
                ,
                $reflectionFunction->getStartLine()
                ,
                $reflectionFunction->getEndLine()
            );

            throw new ContainerException("TypeFactory did not return an object. $error");
        }

        return $instance;

    }
}

/**
 * RFC 4122 Version 4 - Pseudo Random
 *
 * @return string
 * @deprecated Call UUID::v4() directly
 */
function uuid_create()
{
    return UUID::v4();
}
