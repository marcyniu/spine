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
    private $objects = array();
    private $typeFactories = array();

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
        $this->registerParents($object, $reflectionObject);

        $this->registerInterfaces($object, $reflectionObject);

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
        $this->objects[strtolower($type)] = $object;
    }

    /**
     * @param string   $type
     * @param          $type
     * @param          $callableFactory
     * @param callable $callableFactory
     */
    public function registerTypeFactory($type, $callableFactory)
    {
        $reflectionClass = new ReflectionClass($type);

        $this->typeFactories[strtolower($reflectionClass->getName())] = $callableFactory;

        // recurs into parents classes
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->registerTypeFactory($parentClass->getName(), $callableFactory);
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

//        var_dump($className);
        if (!class_exists($className) && !interface_exists($className)) {
            throw new ContainerException("Class/Interface '$className' does not exist");
        }

        $key = strtolower($className); // make lower case here, b/c auto loader might be case sensitive.
        if (!isset($this->objects[$key])) {
            $object = $this->make($className);
            $this->register($object);
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
        $reflectionClass = new ReflectionClass($className);

        $key = strtolower($className);

        if (!$reflectionClass->isInstantiable() && !isset($this->typeFactories[$key])) {
            throw new ContainerException("The type $className  is not instantiable");
        }

        if (isset($this->typeFactories[$key])) {

            $callable           = $this->typeFactories[$key];
            $reflectionFunction = new \ReflectionFunction($callable);
            $signature          = $this->getSignature($reflectionFunction);

//            var_dump($signature);
            $args = array();
            foreach ($signature as $name => $type) {
                $args[$name] = $this->resolve($type);
            }

            $instance = $reflectionFunction->invokeArgs($args);

            if (!is_object($instance)) {
                $error = sprintf(
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

        $constructorReflectMethod = $reflectionClass->getConstructor();

        if ($constructorReflectMethod === null) { // no constructor.
            return $reflectionClass->newInstance();
        }

        $signature = $this->getSignature($constructorReflectMethod);

        $args = array();
        foreach ($signature as $name => $type) {
            $args[$name] = $this->resolve($type);
        }

        return $reflectionClass->newInstanceArgs($args);

    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function getSignature(\ReflectionFunctionAbstract $reflectionMethod)
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
}
