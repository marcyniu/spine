<?php

namespace Spine;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;

/**
 * @since  2014-01-20
 * @author Lance Rushing
 */
class Container
{
    /**
     * @var array <ContainerFactory>
     */
    private $typeFactories = [];

    /**
     * Used to detect circular __construct() dependencies
     *
     * @var array
     */
    private $resolvingClasses = [];

    /**
     * Array of [$callIndex][<ReflectionMethod>, <instance>]
     *
     * Keeps track of any inject*() methods that need to be called after object construction
     *
     * @var array
     */
    private $injectionMethods = [];

    private $callIndex = 0;

    /**
     *
     */
    public function __construct()
    {
        // register itself. Note: be careful depending on the container, only factory like classes should ever need it....
        $this->register($this);
    }

    /**
     * Will invoke the given $callable with all of it's arguments resolved
     *
     * @param $callable
     *
     * @return mixed
     */
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

        $args = $this->resolveArguments($reflectionMethod);

        return $reflectionMethod->invokeArgs($class, $args);
    }

    /**
     * Registers and Object -- fluent
     *
     * @param      $object
     * @param bool $registerParentClasses
     *
     * @return Container
     */
    public function register($object, $registerParentClasses = true)
    {
        $reflectionObject = new ReflectionObject($object);

        $this->_registerType($object, $reflectionObject, $registerParentClasses);

        return $this;
    }

    /**
     * @param string $className class/interface Name
     * @param object $object
     * @param bool   $registerParentClasses
     *
     * @return $this
     */
    public function registerType($className, $object, $registerParentClasses = false)
    {
        if (!is_string($className)) {
            throw new \InvalidArgumentException('\$className must be string');
        }
        $reflectionClass = new ReflectionClass($className);
        $this->_registerType($object, $reflectionClass, $registerParentClasses);

        return $this;
    }

    /**
     * @param ReflectionClass $reflectionClass class/interface the object extends/implements
     * @param object          $object
     * @param bool            $registerParentClasses
     *
     * @return $this
     */
    private function _registerType($object, ReflectionClass $reflectionClass, $registerParentClasses)
    {

        $this->objects[$reflectionClass->getName()] = $object;

        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($object, $reflectionClass);
            $this->registerInterfaces($object, $reflectionClass);
        }
        return $this;
    }

    /**
     * @param string   $type
     * @param callable $callableFactory
     * @param bool     $registerParents Will also register the factory for the parent class of $type
     *
     * @return $this
     */
    public function registerTypeFactory($type, $callableFactory, $registerParents = false)
    {

        $containerFactory                  = new ContainerFactory();
        $containerFactory->factoryMethod   = new ReflectionFunction($callableFactory);
        $containerFactory->reflectionClass = new ReflectionClass($type);

        $this->typeFactories[$containerFactory->reflectionClass->getName()] = $containerFactory;

        if ($registerParents) {
            $this->registerFactoryForParents($containerFactory, $containerFactory->reflectionClass);
            $this->registerFactoryForInterfaces($containerFactory, $containerFactory->reflectionClass);
        }

        return $this;
    }

    /**
     * @param string $className
     *
     * @throws ContainerException
     * @return mixed
     */
    public function resolve($className)
    {
        $reflectionClass = new ReflectionClass($className);

        $this->buildTypeFactoryForConstructor($reflectionClass);

        $object = $this->resolveClassName($reflectionClass->getName());

        return $object;
    }

    private function buildTypeFactoryForConstructor(ReflectionClass $reflectionClass)
    {
        if (!isset($this->typeFactories[$reflectionClass->getName()])) {

            $factory                  = new ContainerFactory();
            $factory->reflectionClass = $reflectionClass;

            $constructorReflectMethod = $reflectionClass->getConstructor();

            if ($constructorReflectMethod === null) { // no constructor.
                $factory->instance = $reflectionClass->newInstance();
            } else {
                $factory->factoryMethod = $constructorReflectMethod;

                $factory->factoryMethodArguments = $this->buildTypeFactoriesForFunctionArguments($constructorReflectMethod);

            }

            $this->typeFactories[$reflectionClass->getName()] = $factory;
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     *
     * @return array
     */
    private function buildTypeFactoriesForFunctionArguments(ReflectionFunctionAbstract $reflectionFunction)
    {
        $arguments = [];
        $signature = $this->getSignature($reflectionFunction);
        /** @var ReflectionClass $signatureReflectionClass */
        foreach ($signature as $argName => $signatureReflectionClass) {
            $arguments[$argName] = $signatureReflectionClass->getName();
            $this->buildTypeFactoryForConstructor($signatureReflectionClass);
        }

        return $arguments;
    }

    /**
     * @param ContainerFactory $containerFactory
     *
     * @return object
     * @throws ContainerException
     */
    private function getFactoryInstance(ContainerFactory $containerFactory)
    {

        if (!is_object($containerFactory->instance)) {

            $reflectionFunction = $containerFactory->factoryMethod;

            $args = [];
            foreach ($containerFactory->factoryMethodArguments as $argClassName) {
                $args[] = $this->resolveClassName($argClassName);
            }

            /** @var ReflectionFunction $reflectionFunction */

            if ($reflectionFunction->isClosure()) {
                $instance = $reflectionFunction->invokeArgs($args);
            } elseif ($reflectionFunction->isConstructor()) {
                $instance = $containerFactory->reflectionClass->newInstanceArgs($args);
            } else {
                $instance = $reflectionFunction->invokeArgs($args);
            }

            if (!is_object($instance)) {
                $error = sprintf("%s (%u:%u)", $reflectionFunction->getFileName(),
                    $reflectionFunction->getStartLine(), $reflectionFunction->getEndLine());
                throw new ContainerException("TypeFactory did not return an object. $error");
            }
            $containerFactory->instance = $instance;
        }

        return $containerFactory->instance;
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
                $reflectionClass = $reflectionParameter->getClass();
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

            if (is_null($reflectionClass)) { // no class for argument
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

            $signature[$reflectionParameter->getName()] = $reflectionClass;
        }
        return $signature;
    }

    /**
     * Will invoke the injection methods
     */
    private function invokeInjectMethods()
    {
        /** @var $injectMethod \ReflectionMethod */
        /** @var mixed $instance */
        while ($injectMethodAndInstance = array_shift($this->injectionMethods[$this->callIndex])) {
            list($injectMethod, $instance) = $injectMethodAndInstance;
            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }

        unset($this->injectionMethods[$this->callIndex]);

        $this->callIndex--;
    }

    /**
     * Instantiates the given className
     *
     * @param $className
     *
     * @throws ContainerException
     * @return object
     */
    private function make($className)
    {
        $this->resolvingClasses[$className] = 1;

        $reflectionClass = new ReflectionClass($className);
        $key             = $reflectionClass->name;

        // Confirm it can be created.
        if (!$reflectionClass->isInstantiable() && !isset($this->typeFactories[$key])) {
            throw new ContainerException("The type " . $reflectionClass->name . " is not instantiable");
        }

        if (isset($this->typeFactories[$key])) {
            $instance = $this->getFactoryInstance($this->typeFactories[$key]);
        } else {
            $instance = $this->createInstance($reflectionClass);
        }

        unset($this->resolvingClasses[$className]);

        $this->_registerType($instance, $reflectionClass, true);
        $this->registerInjectMethods($reflectionClass, $instance);

        return $instance;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function resolveArguments(\ReflectionFunctionAbstract $reflectionMethod)
    {

        $methodArguments = $this->buildTypeFactoriesForFunctionArguments($reflectionMethod);

        $args = [];
        foreach ($methodArguments as $className) {
            $factoryContainer = $this->typeFactories[$className];
            $args[] = $this->getFactoryInstance($factoryContainer);
        }

        return $args;


//            if (isset($this->resolvingClasses[$type])) {
//
//                if ($reflectionMethod instanceof \ReflectionFunction) {
//                    /** @var \ReflectionFunction $reflectionMethod */
//                    $msg = sprintf('Circular Reference Detected. Function defined in "%s:%s" requires type "%s".',
//                        $reflectionMethod->getFileName(), $reflectionMethod->getEndLine(), $type);
//                } else {
//                    /** @var \ReflectionMethod $reflectionMethod */
//                    $msg = sprintf('Circular Reference Detected. Method "%s::%s" defined in "%s:%s requires type "%s".',
//                        $reflectionMethod->class, $reflectionMethod->getName(), $reflectionMethod->getFileName(),
//                        $reflectionMethod->getEndLine(), $type);
//                }
//
//                throw new ContainerException($msg);
//            }


    }

    private function resolveClassName($className)
    {
        /** @var ContainerFactory $factory */
        $factory = $this->typeFactories[$className];
        return $this->getFactoryInstance($factory);;
    }

    /**
     * Will register any methods, who's name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     * @param                 $instance
     */
    private function registerInjectMethods(ReflectionClass $reflectionClass, $instance)
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
            $this->injectionMethods[$this->callIndex][] = [$injectMethod, $instance];
        }
    }

    /**
     * @param                 $object
     * @param ReflectionClass $reflectionClass
     */
    private function registerParents($object, ReflectionClass $reflectionClass)
    {
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->objects[$parentClass->getName()] = $object;
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
            $this->objects[$interfaceName] = $object;
        }
    }

    /**
     * @param ContainerFactory $containerFactory
     * @param ReflectionClass  $reflectionClass
     */
    private function registerFactoryForParents(ContainerFactory $containerFactory, ReflectionClass $reflectionClass)
    {
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass) {
            $this->typeFactories[$parentClass->getName()] = $containerFactory;
            // recurse
            $this->registerFactoryForParents($containerFactory, $parentClass);
        }
    }

    /**
     * @param ContainerFactory $containerFactory
     * @param ReflectionClass  $reflectionClass
     */
    private function registerFactoryForInterfaces(ContainerFactory $containerFactory, ReflectionClass $reflectionClass)
    {
        $interfaceNames = $reflectionClass->getInterfaceNames();
        foreach ($interfaceNames as $interfaceName) {
            $this->typeFactories[$interfaceName] = $containerFactory;
        }
    }

    private function prepInjectionMethodList()
    {
        $this->callIndex++;
        $this->injectionMethods[$this->callIndex] = [];
    }
}

class ContainerFactory
{
    /**
     * @var ReflectionClass
     */
    public $reflectionClass;
    public $instance;
    public $factoryMethodArguments = [];
    public $injectionMethods;

    /**
     * @var \ReflectionFunction|\ReflectionMethod
     */
    public $factoryMethod;
}
