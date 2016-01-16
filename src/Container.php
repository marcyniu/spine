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
     * @var array <InstanceWrapper>
     */
    private $instanceWrappers = [];

    /**
     * Used to detect circular __construct() dependencies
     *
     * @var array
     */
    private $resolvingClasses = [];

    /**
     * Array of [<instance>, <ReflectionMethod>]
     *
     * Track inject*() methods that need to be called after object construction
     *
     * @var array
     */
    private $pendingInjectionMethods = [];


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
        $args               = $this->resolveArguments($reflectionFunction);
        return $reflectionFunction->invokeArgs($args);
    }

    /**
     * @param mixed  $class Either a class name, or an object.
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
     * Register an Object -- fluent
     *
     * @param      $object
     * @param bool $registerParentClasses
     *
     * @return Container
     */
    public function register($object, $registerParentClasses = true)
    {
        $reflectionObject = new ReflectionObject($object);

        $this->createAndRegisterInstanceWrapper($object, $reflectionObject, $registerParentClasses);

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
        $reflectionClass = new ReflectionClass($className);
        $this->createAndRegisterInstanceWrapper($object, $reflectionClass, $registerParentClasses);
        return $this;

    }


    /**
     * @param string   $className
     * @param callable $callableFactory
     * @param bool     $registerParents Will also register the factory for the parent class of $type
     *
     * @return $this
     */
    public function registerTypeFactory($className, $callableFactory, $registerParents = false)
    {

        $reflectionClass = new ReflectionClass($className);
        $this->createAndRegisterInstanceWrapper(null, $reflectionClass, $registerParents,
            new ReflectionFunction($callableFactory));

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

        $this->buildInstanceWrapperForClass($reflectionClass);

        $object = $this->resolveReflectionClass($reflectionClass);
        $this->invokePendingInjectFactories();
        return $object;
    }


    /**
     * @param object                     $object
     * @param ReflectionClass            $reflectionClass class/interface the object extends/implements
     * @param bool                       $registerParentClasses
     * @param ReflectionFunctionAbstract $factoryReflectionFunction
     * @return InstanceWrapper $instanceWrapper
     */
    private function createAndRegisterInstanceWrapper($object, ReflectionClass $reflectionClass, $registerParentClasses, ReflectionFunctionAbstract $factoryReflectionFunction = null)
    {
        $instanceWrapper                  = new InstanceWrapper();
        $instanceWrapper->instance        = $object;
        $instanceWrapper->reflectionClass = $reflectionClass;

        if ($factoryReflectionFunction) {
            $instanceWrapper->factoryMethod          = $factoryReflectionFunction;
            $instanceWrapper->factoryMethodArguments = $this->buildArgumentsInstanceWrappersForFunction($factoryReflectionFunction);
        }


        $this->registerInstanceWrapperForClass($instanceWrapper, $reflectionClass);


        $injectionMethods = $this->getInjectMethods($reflectionClass);
        /** @var ReflectionMethod $injectionReflectionMethod */
        foreach ($injectionMethods as $injectionReflectionMethod) {
            $instanceWrapper->injectionMethods[] = $injectionReflectionMethod;
        }

        // register parent classes also
        if ($registerParentClasses) {
            $this->registerParents($reflectionClass, $instanceWrapper);
            $this->registerInterfaces($reflectionClass, $instanceWrapper);
        }
        return $instanceWrapper;
    }

    private function buildInstanceWrapperForClass(ReflectionClass $reflectionClass)
    {
        if (!isset($this->instanceWrappers[$reflectionClass->getName()])) {

            $instanceWrapper = $this->createAndRegisterInstanceWrapper(null, $reflectionClass, false);

            $constructorReflectMethod = $reflectionClass->getConstructor();

            if ($constructorReflectMethod === null) { // no constructor.
                // go ahead an instantiate
                $instanceWrapper->instance = $reflectionClass->newInstance();
            } else {
                $instanceWrapper->factoryMethod = $constructorReflectMethod;
                $instanceWrapper->factoryMethodArguments = $this->buildArgumentsInstanceWrappersForFunction($instanceWrapper->factoryMethod);
            }
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     *
     * @return array
     */
    private function buildArgumentsInstanceWrappersForFunction(ReflectionFunctionAbstract $reflectionFunction)
    {
        $arguments = [];
        $signature = $this->getSignature($reflectionFunction);
        /** @var ReflectionClass $signatureReflectionClass */
        foreach ($signature as $argName => $signatureReflectionClass) {
            $arguments[$argName] = $signatureReflectionClass;
            $this->buildInstanceWrapperForClass($signatureReflectionClass);
        }

        return $arguments;
    }

    /**
     * @param InstanceWrapper $factoryWrapper
     *
     * @return object
     * @throws ContainerException
     */
    private function getInstanceFromWrapper(InstanceWrapper $factoryWrapper)
    {

        if (!is_object($factoryWrapper->instance)) {

            $reflectionFunction = $factoryWrapper->factoryMethod;

            $args = [];
            foreach ($factoryWrapper->factoryMethodArguments as $reflectionClass) {
                $args[] = $this->resolveReflectionClass($reflectionClass);
            }

            /** @var ReflectionFunction|ReflectionMethod $reflectionFunction */
            if ($reflectionFunction->isClosure()) {
                $instance = $reflectionFunction->invokeArgs($args);
            } elseif ($reflectionFunction->isConstructor()) {
                if (!$reflectionFunction->getDeclaringClass()->isInstantiable()) {
                    throw new ContainerException("Cannot call private constructor for " . $reflectionFunction->getDeclaringClass()->getName());
                }
                $instance = $factoryWrapper->reflectionClass->newInstanceArgs($args);
            } else {
                $instance = $reflectionFunction->invokeArgs($args);
            }

            if (!is_object($instance)) {
                $error = sprintf("%s (%u:%u)", $reflectionFunction->getFileName(), $reflectionFunction->getStartLine(),
                    $reflectionFunction->getEndLine());
                throw new ContainerException("InstanceWrapper did not return an object. $error");
            }
            $factoryWrapper->instance = $instance;

        }

        while ($pendingFactory = array_shift($factoryWrapper->injectionMethods)) {
            $this->pendingInjectionMethods[] = [$factoryWrapper->instance, $pendingFactory];
        }

        return $factoryWrapper->instance;
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
                $msg = sprintf("Method '%s:%s()' has unknown type hint for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name, $reflectionParameter->name,
                    $reflectionMethod->getFileName(), $reflectionMethod->getEndLine());
                throw new ContainerException($msg, 999, $e);
            }

            if (is_null($reflectionClass)) { // no class for argument
                if ($reflectionParameter->isOptional()) { // has default value
                    continue; // skip adding this parameter.. will break for mixed signatures.
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $msg = sprintf("Method '%s:%s()' has no type hint  and no default value for '%s' parameter, found in %s on line %u",
                    $reflectionParameter->getDeclaringFunction()->class,
                    $reflectionParameter->getDeclaringFunction()->name, $reflectionParameter->name,
                    $reflectionMethod->getFileName(), $reflectionMethod->getEndLine());
                throw new ContainerException($msg);

            }

            $signature[$reflectionParameter->getName()] = $reflectionClass;
        }
        return $signature;
    }

    /**
     * @param \ReflectionFunctionAbstract $reflectionMethod
     *
     * @return array
     * @throws ContainerException
     */
    private function resolveArguments(\ReflectionFunctionAbstract $reflectionMethod)
    {

        $methodArguments = $this->buildArgumentsInstanceWrappersForFunction($reflectionMethod);

        $args = [];
        /** @var ReflectionClass $reflectionClass */
        foreach ($methodArguments as $reflectionClass) {
            $className = $reflectionClass->getName();
            if (!isset($this->instanceWrappers[$className])) {
                throw new ContainerException("No factory wrappers for '$className'");
            }
            $factoryContainer = $this->instanceWrappers[$className];
            $args[]           = $this->getInstanceFromWrapper($factoryContainer);
        }

        return $args;

    }

    private function resolveReflectionClass(ReflectionClass $reflectionClass)
    {
        if (isset($this->resolvingClasses[$reflectionClass->getName()])) {
            $classes = join(" -> ", array_keys($this->resolvingClasses)) . " -> " . $reflectionClass->getName();
            throw new ContainerException("Circular dependency detected. $classes");
        }
        $this->resolvingClasses[$reflectionClass->getName()] = 1;

        /** @var InstanceWrapper $factoryWrapper */
        $factoryWrapper = $this->instanceWrappers[$reflectionClass->getName()];
        $object         = $this->getInstanceFromWrapper($factoryWrapper);;

        unset($this->resolvingClasses[$reflectionClass->getName()]);

        return $object;
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param InstanceWrapper $instanceWrapper
     */
    private function registerParents(ReflectionClass $reflectionClass, InstanceWrapper $instanceWrapper)
    {
        $parentReflectionClass = $reflectionClass->getParentClass();
        if ($parentReflectionClass) {
            $this->registerInstanceWrapperForClass($instanceWrapper, $parentReflectionClass);
            $this->registerParents($parentReflectionClass, $instanceWrapper);
        }
    }

    /**
     * @param ReflectionClass $reflectionClass
     * @param InstanceWrapper $instanceWrapper
     */
    private function registerInterfaces(ReflectionClass $reflectionClass, InstanceWrapper $instanceWrapper)
    {
        $interfaces = $reflectionClass->getInterfaces();
        /** @var ReflectionClass $interfacesReflectionClass */
        foreach ($interfaces as $interfacesReflectionClass) {
            $this->registerInstanceWrapperForClass($instanceWrapper, $interfacesReflectionClass);
        }
    }


    private function registerInstanceWrapperForClass(InstanceWrapper $instanceWrapper, ReflectionClass $reflectionClass)
    {
        $className                          = $reflectionClass->getName();
        $this->instanceWrappers[$className] = $instanceWrapper;
    }

    /**
     * Will return any methods, whose name starts with 'inject'
     *
     * @param ReflectionClass $reflectionClass
     *
     * @return array of <ReflectionMethod>
     */
    private function getInjectMethods(ReflectionClass $reflectionClass)
    {
        // find methods matching /^inject/
        $injectMethods = (array_filter($reflectionClass->getMethods(), function (\ReflectionMethod $reflectionMethod) {
            return (preg_match("/^inject/", $reflectionMethod->getName()));
        }));

        return $injectMethods;
    }

    /**
     * Will invoke the injection methods
     */
    private function invokePendingInjectFactories()
    {
        /** @var $injectMethod \ReflectionMethod */
        /** @var mixed $instance */
        while ($instanceAndMethod = array_shift($this->pendingInjectionMethods)) {
            list($instance, $injectMethod) = $instanceAndMethod;

            $args = $this->resolveArguments($injectMethod);
            $injectMethod->invokeArgs($instance, $args);
        }
    }

}

class InstanceWrapper
{
    /**
     * @var ReflectionClass
     */
    public $reflectionClass;

    /**
     * @var object
     */
    public $instance;

    /**
     * @var array
     */
    public $factoryMethodArguments = [];

    /**
     * @var [<Callable>]
     */
    public $injectionMethods = [];

    /**
     * @var \ReflectionFunction|\ReflectionMethod
     */
    public $factoryMethod;
}
