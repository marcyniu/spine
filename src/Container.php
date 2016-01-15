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
     * @var array <TypeFactoryWrapper>
     */
    private $typeFactoryWrappers = [];

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

        $typeFactoryWrapper = new TypeFactoryWrapper();
        $typeFactoryWrapper->instance = $object;
        $typeFactoryWrapper->reflectionClass = new ReflectionClass($className);;

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);


        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($typeFactoryWrapper->reflectionClass, $typeFactoryWrapper);
            $this->registerInterfaces($typeFactoryWrapper->reflectionClass, $typeFactoryWrapper);
        }
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
        $typeFactoryWrapper = new TypeFactoryWrapper();
        $typeFactoryWrapper->instance = $object;
        $typeFactoryWrapper->reflectionClass = $reflectionClass;

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);


        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($reflectionClass, $typeFactoryWrapper);
            $this->registerInterfaces($reflectionClass, $typeFactoryWrapper);
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

        $typeFactoryWrapper                  = new TypeFactoryWrapper();
        $typeFactoryWrapper->factoryMethod   = new ReflectionFunction($callableFactory);
        $typeFactoryWrapper->reflectionClass = new ReflectionClass($type);

        $this->_registerTypeFactoryWrapper($typeFactoryWrapper);

        if ($registerParents) {
            $this->registerParents($typeFactoryWrapper->reflectionClass , $typeFactoryWrapper);
            $this->registerInterfaces($typeFactoryWrapper->reflectionClass , $typeFactoryWrapper);
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
        if (!isset($this->typeFactoryWrappers[$reflectionClass->getName()])) {

            $factory                  = new TypeFactoryWrapper();
            $factory->reflectionClass = $reflectionClass;

            $constructorReflectMethod = $reflectionClass->getConstructor();

            if ($constructorReflectMethod === null) { // no constructor.
                $factory->instance = $reflectionClass->newInstance();
            } else {
                $factory->factoryMethod = $constructorReflectMethod;

                $factory->factoryMethodArguments = $this->buildTypeFactoriesForFunctionArguments($constructorReflectMethod);

            }

            $this->typeFactoryWrappers[$reflectionClass->getName()] = $factory;
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
     * @param TypeFactoryWrapper $containerFactory
     *
     * @return object
     * @throws ContainerException
     */
    private function getFactoryInstance(TypeFactoryWrapper $containerFactory)
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
                throw new ContainerException("TypeFactoryWrapper did not return an object. $error");
            }
            $containerFactory->instance = $instance;
        }

        return $containerFactory->instance;
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
            $factoryContainer = $this->typeFactoryWrappers[$className];
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
        /** @var TypeFactoryWrapper $factory */
        $factory = $this->typeFactoryWrappers[$className];
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
     * @param ReflectionClass    $reflectionClass
     * @param TypeFactoryWrapper $typeFactoryWrapper
     */
    private function registerParents(ReflectionClass $reflectionClass, TypeFactoryWrapper $typeFactoryWrapper)
    {
        $parentReflectionClass = $reflectionClass->getParentClass();
        if ($parentReflectionClass) {
            $this->_registerTypeFactoryWrapper($typeFactoryWrapper, $parentReflectionClass);
            $this->registerParents($parentReflectionClass, $typeFactoryWrapper);
        }
    }

    /**
     * @param ReflectionClass    $reflectionClass
     * @param TypeFactoryWrapper $typeFactoryWrapper
     */
    private function registerInterfaces(ReflectionClass $reflectionClass, TypeFactoryWrapper $typeFactoryWrapper)
    {
        $interfaces = $reflectionClass->getInterfaces();
        /** @var ReflectionClass $interfacesReflectionClass */
        foreach ($interfaces as $interfacesReflectionClass) {
            $this->_registerTypeFactoryWrapper($typeFactoryWrapper, $interfacesReflectionClass);
        }
    }



    private function _registerTypeFactoryWrapper(TypeFactoryWrapper $typeFactoryContainer, ReflectionClass $reflectionClass = null)
    {
        if (empty($reflectionClass)) {
            $reflectionClass = $typeFactoryContainer->reflectionClass;
        }

        $className = $reflectionClass->getName();
        $this->typeFactoryWrappers[$className] = $typeFactoryContainer;
    }
}

class TypeFactoryWrapper
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
